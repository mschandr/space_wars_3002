<?php

namespace App\Console\Tui\Screens;

use App\Console\Services\LocationValidator;
use App\Console\Tui\Traits\TuiRendering;
use App\Console\Tui\Widgets\ControlsWidget;
use App\Console\Tui\Widgets\HeaderWidget;
use App\Console\Tui\Widgets\LocationPanelWidget;
use App\Console\Tui\Widgets\ShipStatsPanelWidget;
use PhpTui\Tui\Layout\Constraint;
use PhpTui\Tui\Model\Direction;

class DashboardScreen extends AbstractTuiScreen
{
    use TuiRendering;

    public function render($display): void
    {
        $area = $display->area();

        // Create main layout: Header | Content | Footer
        $mainLayout = $this->createLayout(
            Direction::Vertical,
            [
                Constraint::length(3),  // Header
                Constraint::min(0),      // Content
                Constraint::length(5),  // Footer/Controls
            ],
            $area
        );

        // Render header
        $header = HeaderWidget::create();
        $display->render($header, $mainLayout[0]);

        // Split content into two columns
        $contentLayout = $this->createLayout(
            Direction::Horizontal,
            [
                Constraint::percentage(50), // Location
                Constraint::percentage(50), // Ship Stats
            ],
            $mainLayout[1]
        );

        // Render location panel
        $locationPanel = LocationPanelWidget::create($this->player);
        $display->render($locationPanel, $contentLayout[0]);

        // Render ship stats panel
        ShipStatsPanelWidget::render(
            $this->player->activeShip,
            $this->player,
            $contentLayout[1],
            $display
        );

        // Render controls
        $this->renderControls($display, $mainLayout[2]);
    }

    public function handleKeyEvent($keyCode): ?string
    {
        // Handle character keys
        if ($keyCode instanceof \PhpTui\Term\KeyCode\Char) {
            return match ($keyCode->char) {
                'q' => 'quit',
                'w' => 'travel',
                't' => 'trade',
                'p' => 'upgrades',
                'l' => 'plans',
                's' => 'ship_info',
                default => null,
            };
        }

        return null;
    }

    private function renderControls($display, $area): void
    {
        $atTradingHub = LocationValidator::isAtTradingHub($this->player);
        $atPlansHub = LocationValidator::isAtPlansHub($this->player);

        $line1 = "[w] Warp Travel  [t] Trade  [p] Upgrades";
        if ($atPlansHub) {
            $line1 .= "  [l] Plans";
        }
        $line1 .= "  [s] Ship Info  [q] Quit";

        $controls = ControlsWidget::create(
            $line1,
            $this->errorMessage,
            $this->statusMessage
        );

        $display->render($controls, $area);
    }
}
