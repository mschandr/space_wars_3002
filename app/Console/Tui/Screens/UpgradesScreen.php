<?php

namespace App\Console\Tui\Screens;

use App\Console\Services\LocationValidator;
use App\Console\Tui\Handlers\UpgradeHandler;
use App\Console\Tui\Traits\TuiRendering;
use App\Console\Tui\Widgets\ControlsWidget;
use App\Services\ShipUpgradeService;
use PhpTui\Tui\Extension\Core\Widget\BlockWidget;
use PhpTui\Tui\Extension\Core\Widget\ParagraphWidget;
use PhpTui\Tui\Layout\Constraint;
use PhpTui\Tui\Model\Direction;
use PhpTui\Tui\Model\Widget\Borders;
use PhpTui\Tui\Model\Widget\BorderType;
use PhpTui\Tui\Style\Color;
use PhpTui\Tui\Style\Style;
use PhpTui\Tui\Text\Line;

class UpgradesScreen extends AbstractTuiScreen
{
    use TuiRendering;

    private UpgradeHandler $upgradeHandler;

    public function __construct($player)
    {
        parent::__construct($player);
        $this->upgradeHandler = new UpgradeHandler($player, app(ShipUpgradeService::class));
    }

    public function render($display): void
    {
        $area = $display->area();

        // Check if player is at a trading hub
        if (!LocationValidator::isAtTradingHub($this->player)) {
            $this->renderError($display, $area, "No Trading Hub", "You must be at a trading hub to purchase upgrades.");
            return;
        }

        $upgradeInfo = $this->upgradeHandler->getUpgradeInfo();

        // Create layout
        $layout = $this->createLayout(
            Direction::Vertical,
            [
                Constraint::length(5),   // Header
                Constraint::min(0),      // Upgrades list
                Constraint::length(5),   // Controls
            ],
            $area
        );

        $this->renderUpgradesHeader($display, $layout[0]);
        $this->renderUpgradesList($display, $layout[1], $upgradeInfo);
        $this->renderUpgradesControls($display, $layout[2]);
    }

    public function handleKeyEvent($keyCode): ?string
    {
        // Handle arrow keys
        if ($keyCode instanceof \PhpTui\Term\KeyCode\Up) {
            $this->selectedIndex = max(0, $this->selectedIndex - 1);
            return null;
        }

        if ($keyCode instanceof \PhpTui\Term\KeyCode\Down) {
            $this->selectedIndex = min(5, $this->selectedIndex + 1); // 6 components (0-5)
            return null;
        }

        // Handle enter
        if ($keyCode instanceof \PhpTui\Term\KeyCode\Enter) {
            $this->executeUpgrade();
            return null;
        }

        // Handle escape
        if ($keyCode instanceof \PhpTui\Term\KeyCode\Esc) {
            return 'back';
        }

        return null;
    }

    private function executeUpgrade(): void
    {
        $upgradeInfo = $this->upgradeHandler->getUpgradeInfo();
        $components = array_keys($upgradeInfo);

        if (!isset($components[$this->selectedIndex])) {
            return;
        }

        $component = $components[$this->selectedIndex];
        $result = $this->upgradeHandler->executeUpgrade($component);

        if ($result['success']) {
            $this->setStatus($result['message']);
            $this->reloadPlayer();
        } else {
            $this->setError($result['message']);
        }
    }

    private function renderUpgradesHeader($display, $area): void
    {
        $lines = [
            Line::fromString("Credits: " . number_format($this->player->credits, 2))
                ->style(Style::default()->fg(Color::Yellow)),
            Line::fromString("Select a component to upgrade")
                ->style(Style::default()->fg(Color::Cyan)),
        ];

        $block = BlockWidget::default()
            ->borders(Borders::ALL)
            ->borderType(BorderType::Rounded)
            ->title("SHIP COMPONENT UPGRADES")
            ->titleStyle(Style::default()->fg(Color::Yellow))
            ->widget(ParagraphWidget::fromLines($lines));

        $display->render($block, $area);
    }

    private function renderUpgradesList($display, $area, array $upgradeInfo): void
    {
        $items = [];
        $components = array_keys($upgradeInfo);

        foreach ($components as $index => $component) {
            $info = $upgradeInfo[$component];
            $isSelected = $index === $this->selectedIndex;

            // Component name
            $displayName = match($component) {
                'max_fuel' => 'Max Fuel',
                'max_hull' => 'Max Hull',
                'cargo_hold' => 'Cargo Hold',
                'warp_drive' => 'Warp Drive',
                default => ucwords(str_replace('_', ' ', $component))
            };

            // Build the line
            $statusText = $info['can_upgrade']
                ? sprintf(
                    "Lvl %d/%d → Lvl %d  Cost: %s cr",
                    $info['current_level'],
                    $info['max_level'],
                    $info['current_level'] + 1,
                    number_format($info['upgrade_cost'])
                )
                : sprintf(
                    "Lvl %d/%d  [MAXED OUT]",
                    $info['current_level'],
                    $info['max_level']
                );

            $line = sprintf(
                "[%d] %-15s Value: %4d  %s",
                $index + 1,
                $displayName,
                $info['current_value'],
                $statusText
            );

            $style = Style::default()->fg(
                $info['can_upgrade'] ? Color::White : Color::DarkGray
            );
            if ($isSelected) {
                $style = $style->bg(Color::Blue);
            }

            $items[] = Line::fromString($line)->style($style);

            // Show additional levels from plans if any
            if ($info['additional_levels'] > 0) {
                $bonusText = "    (+{$info['additional_levels']} bonus levels from plans)";
                $items[] = Line::fromString($bonusText)
                    ->style(Style::default()->fg(Color::Magenta));
            }
        }

        $block = BlockWidget::default()
            ->borders(Borders::ALL)
            ->title("AVAILABLE UPGRADES")
            ->titleStyle(Style::default()->fg(Color::Green))
            ->widget(ParagraphWidget::fromLines($items));

        $display->render($block, $area);
    }

    private function renderUpgradesControls($display, $area): void
    {
        $controls = ControlsWidget::create(
            "[↑/↓] Navigate  [Enter] Purchase Upgrade  [Esc] Back",
            $this->errorMessage,
            $this->statusMessage
        );

        $display->render($controls, $area);
    }
}
