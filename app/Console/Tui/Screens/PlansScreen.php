<?php

namespace App\Console\Tui\Screens;

use App\Console\Services\LocationValidator;
use App\Console\Tui\Handlers\PlanPurchaseHandler;
use App\Console\Tui\Traits\TuiRendering;
use App\Console\Tui\Widgets\ControlsWidget;
use PhpTui\Tui\Extension\Core\Widget\BlockWidget;
use PhpTui\Tui\Extension\Core\Widget\ParagraphWidget;
use PhpTui\Tui\Layout\Constraint;
use PhpTui\Tui\Model\Direction;
use PhpTui\Tui\Model\Widget\Borders;
use PhpTui\Tui\Model\Widget\BorderType;
use PhpTui\Tui\Style\Color;
use PhpTui\Tui\Style\Style;
use PhpTui\Tui\Text\Line;

class PlansScreen extends AbstractTuiScreen
{
    use TuiRendering;

    private PlanPurchaseHandler $planHandler;

    public function __construct($player)
    {
        parent::__construct($player);
        $this->planHandler = new PlanPurchaseHandler($player);
    }

    public function render($display): void
    {
        $area = $display->area();

        // Check if player is at a plans hub
        if (!LocationValidator::isAtPlansHub($this->player)) {
            $this->renderError($display, $area, "No Plans Available", "You must be at a trading hub with plans to purchase upgrade plans.");
            return;
        }

        $tradingHub = LocationValidator::getTradingHub($this->player);
        if (!$tradingHub) {
            $this->renderError($display, $area, "No Trading Hub", "Trading hub not available.");
            return;
        }

        // Load available plans
        $plans = $tradingHub->plans;

        if ($plans->isEmpty()) {
            $this->renderError($display, $area, "No Plans Available", "This trading hub has no upgrade plans for sale.");
            return;
        }

        // Create layout
        $layout = $this->createLayout(
            Direction::Vertical,
            [
                Constraint::length(5),   // Header
                Constraint::min(0),      // Plans list
                Constraint::length(5),   // Controls
            ],
            $area
        );

        $this->renderPlansHeader($display, $layout[0], $tradingHub);
        $this->renderPlansList($display, $layout[1], $plans);
        $this->renderPlansControls($display, $layout[2]);
    }

    public function handleKeyEvent($keyCode): ?string
    {
        // Handle arrow keys
        if ($keyCode instanceof \PhpTui\Term\KeyCode\Up) {
            $this->selectedIndex = max(0, $this->selectedIndex - 1);
            return null;
        }

        if ($keyCode instanceof \PhpTui\Term\KeyCode\Down) {
            $tradingHub = LocationValidator::getTradingHub($this->player);
            if ($tradingHub) {
                $maxIndex = min(8, $tradingHub->plans->count() - 1);
                $this->selectedIndex = min($maxIndex, $this->selectedIndex + 1);
            }
            return null;
        }

        // Handle enter
        if ($keyCode instanceof \PhpTui\Term\KeyCode\Enter) {
            $this->executePurchase();
            return null;
        }

        // Handle escape
        if ($keyCode instanceof \PhpTui\Term\KeyCode\Esc) {
            return 'back';
        }

        return null;
    }

    private function executePurchase(): void
    {
        $tradingHub = LocationValidator::getTradingHub($this->player);
        if (!$tradingHub) {
            return;
        }

        $plans = $tradingHub->plans;
        $displayPlans = $plans->take(9);

        if (!isset($displayPlans[$this->selectedIndex])) {
            return;
        }

        $plan = $displayPlans[$this->selectedIndex];
        $result = $this->planHandler->executePurchase($plan);

        if ($result['success']) {
            $this->setStatus($result['message']);
            $this->reloadPlayer();
        } else {
            $this->setError($result['message']);
        }
    }

    private function renderPlansHeader($display, $area, $tradingHub): void
    {
        $lines = [
            Line::fromString("Trading Hub: " . $tradingHub->name)
                ->style(Style::default()->fg(Color::Magenta)),
            Line::fromString("Credits: " . number_format($this->player->credits, 2))
                ->style(Style::default()->fg(Color::Yellow)),
        ];

        $block = BlockWidget::default()
            ->borders(Borders::ALL)
            ->borderType(BorderType::Rounded)
            ->title("UPGRADE PLANS SHOP")
            ->titleStyle(Style::default()->fg(Color::Yellow))
            ->widget(ParagraphWidget::fromLines($lines));

        $display->render($block, $area);
    }

    private function renderPlansList($display, $area, $plans): void
    {
        $items = [];
        $displayPlans = $plans->take(9);

        foreach ($displayPlans as $index => $plan) {
            $isSelected = $index === $this->selectedIndex;
            $ownedCount = $this->planHandler->getOwnedCount($plan->id);
            $currentBonus = $this->planHandler->calculateBonus($plan, $ownedCount);
            $projectedBonus = $this->planHandler->calculateProjectedBonus($plan);

            // Plan name and tier
            $line1 = sprintf(
                "[%d] %s",
                $index + 1,
                strtoupper($plan->getFullName())
            );

            $style1 = Style::default()->fg(Color::Cyan);
            if ($isSelected) {
                $style1 = $style1->bg(Color::Blue);
            }
            $items[] = Line::fromString($line1)->style($style1);

            // Description
            $items[] = Line::fromString("    " . $plan->description)
                ->style(Style::default()->fg(Color::White));

            // Grants line
            $grantsText = sprintf(
                "    Grants: +%d %s upgrade levels  |  Price: %s cr",
                $plan->additional_levels,
                $plan->getComponentDisplayName(),
                number_format($plan->price, 2)
            );
            $items[] = Line::fromString($grantsText)
                ->style(Style::default()->fg(Color::Yellow));

            // Ownership status
            if ($ownedCount > 0) {
                $ownText = sprintf(
                    "    You own: %dx (Current bonus: +%d, After purchase: +%d)",
                    $ownedCount,
                    $currentBonus,
                    $projectedBonus
                );
                $items[] = Line::fromString($ownText)
                    ->style(Style::default()->fg(Color::Green));
            } else {
                $ownText = sprintf(
                    "    You own: 0 (Current bonus: +0, After purchase: +%d)",
                    $projectedBonus
                );
                $items[] = Line::fromString($ownText)
                    ->style(Style::default()->fg(Color::DarkGray));
            }

            // Add spacing
            $items[] = Line::fromString("");
        }

        $block = BlockWidget::default()
            ->borders(Borders::ALL)
            ->title("AVAILABLE PLANS")
            ->titleStyle(Style::default()->fg(Color::Green))
            ->widget(ParagraphWidget::fromLines($items));

        $display->render($block, $area);
    }

    private function renderPlansControls($display, $area): void
    {
        $controls = ControlsWidget::create(
            "[↑/↓] Navigate  [Enter] Purchase Plan  [Esc] Back",
            $this->errorMessage,
            $this->statusMessage
        );

        $display->render($controls, $area);
    }
}
