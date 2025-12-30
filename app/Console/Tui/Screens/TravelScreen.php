<?php

namespace App\Console\Tui\Screens;

use App\Console\Tui\Handlers\TravelHandler;
use App\Console\Tui\Traits\TuiRendering;
use App\Console\Tui\Widgets\ControlsWidget;
use App\Console\Tui\Widgets\GateListWidget;
use PhpTui\Tui\Extension\Core\Widget\BlockWidget;
use PhpTui\Tui\Extension\Core\Widget\ParagraphWidget;
use PhpTui\Tui\Layout\Constraint;
use PhpTui\Tui\Model\Direction;
use PhpTui\Tui\Model\Widget\Borders;
use PhpTui\Tui\Model\Widget\BorderType;
use PhpTui\Tui\Style\Color;
use PhpTui\Tui\Style\Style;
use PhpTui\Tui\Text\Line;

class TravelScreen extends AbstractTuiScreen
{
    use TuiRendering;

    private TravelHandler $travelHandler;

    public function __construct($player)
    {
        parent::__construct($player);
        $this->travelHandler = new TravelHandler($player);
    }

    public function render($display): void
    {
        $area = $display->area();
        $location = $this->player->currentLocation;

        if (!$location) {
            $this->renderError($display, $area, "No current location", "You must be at a location to travel.");
            return;
        }

        // Load available warp gates
        $gates = $location->outgoingGates()
            ->with('destinationPoi.tradingHub')
            ->where('is_hidden', false)
            ->where('status', 'active')
            ->get();

        if ($gates->isEmpty()) {
            $this->renderError($display, $area, "No warp gates available", "There are no warp gates from this location.");
            return;
        }

        // Create layout
        $layout = $this->createLayout(
            Direction::Vertical,
            [
                Constraint::length(5),   // Header
                Constraint::min(0),      // Gate list
                Constraint::length(4),   // Controls
            ],
            $area
        );

        $this->renderTravelHeader($display, $layout[0]);
        $this->renderGateList($display, $layout[1], $gates);
        $this->renderTravelControls($display, $layout[2]);
    }

    public function handleKeyEvent($keyCode): ?string
    {
        // Handle arrow keys
        if ($keyCode instanceof \PhpTui\Term\KeyCode\Up) {
            $this->handleUp();
            return null;
        }

        if ($keyCode instanceof \PhpTui\Term\KeyCode\Down) {
            $this->handleDown();
            return null;
        }

        // Handle enter
        if ($keyCode instanceof \PhpTui\Term\KeyCode\Enter) {
            $this->executeTravel();
            return null;
        }

        // Handle escape
        if ($keyCode instanceof \PhpTui\Term\KeyCode\Esc) {
            return 'back';
        }

        return null;
    }

    private function handleUp(): void
    {
        $this->selectedIndex = max(0, $this->selectedIndex - 1);
    }

    private function handleDown(): void
    {
        $location = $this->player->currentLocation;
        if ($location) {
            $gateCount = $location->outgoingGates()
                ->where('is_hidden', false)
                ->where('status', 'active')
                ->count();

            if ($gateCount > 0) {
                $this->selectedIndex = min($gateCount - 1, $this->selectedIndex + 1);
            }
        }
    }

    private function executeTravel(): void
    {
        $location = $this->player->currentLocation;
        $gates = $location->outgoingGates()
            ->with('destinationPoi')
            ->where('is_hidden', false)
            ->where('status', 'active')
            ->get();

        if (!isset($gates[$this->selectedIndex])) {
            return;
        }

        $gate = $gates[$this->selectedIndex];
        $result = $this->travelHandler->executeTravel($gate);

        if ($result['success']) {
            $this->setStatus($result['message']);
        } else {
            $this->setError($result['message']);
        }
    }

    private function renderTravelHeader($display, $area): void
    {
        $ship = $this->player->activeShip;
        $location = $this->player->currentLocation;

        $lines = [
            Line::fromString("Current Location: " . $location->name)
                ->style(Style::default()->fg(Color::Cyan)),
            Line::fromString("Fuel: {$ship->current_fuel}/{$ship->max_fuel}")
                ->style(Style::default()->fg(
                    $ship->current_fuel > $ship->max_fuel * 0.7 ? Color::Green : Color::Yellow
                )),
        ];

        $block = BlockWidget::default()
            ->borders(Borders::ALL)
            ->borderType(BorderType::Rounded)
            ->title("WARP GATE TRAVEL")
            ->titleStyle(Style::default()->fg(Color::Yellow))
            ->widget(ParagraphWidget::fromLines($lines));

        $display->render($block, $area);
    }

    private function renderGateList($display, $area, $gates): void
    {
        $gateList = GateListWidget::create(
            $gates,
            $this->player->activeShip,
            $this->selectedIndex
        );

        $display->render($gateList, $area);
    }

    private function renderTravelControls($display, $area): void
    {
        $controls = ControlsWidget::create(
            "[↑/↓] Navigate  [Enter] Travel  [Esc] Back",
            $this->errorMessage,
            $this->statusMessage
        );

        $display->render($controls, $area);
    }
}
