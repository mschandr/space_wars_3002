<?php

namespace App\Console\Tui\Screens;

use App\Console\Services\LocationValidator;
use App\Console\Tui\Handlers\TradeTransactionHandler;
use App\Console\Tui\Traits\TuiEventHandling;
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

class TradeScreen extends AbstractTuiScreen
{
    use TuiRendering;
    use TuiEventHandling;

    private TradeTransactionHandler $tradeHandler;
    private string $tradeMode = 'buy'; // 'buy' or 'sell'
    private bool $inputMode = false;
    private string $inputBuffer = '';

    public function __construct($player)
    {
        parent::__construct($player);
        $this->tradeHandler = new TradeTransactionHandler($player);
    }

    public function render($display): void
    {
        $area = $display->area();

        // Check if player is at a trading hub
        if (!LocationValidator::isAtTradingHub($this->player)) {
            $this->renderError($display, $area, "No Trading Hub", "You must be at a trading hub to trade minerals.");
            return;
        }

        $tradingHub = LocationValidator::getTradingHub($this->player);
        if (!$tradingHub) {
            $this->renderError($display, $area, "No Trading Hub", "Trading hub not available.");
            return;
        }

        // Load data
        $ship = $this->player->activeShip;
        $hubInventories = $tradingHub->inventories()->with('mineral')->where('quantity', '>', 0)->get();
        $playerCargo = $ship->cargo()->with('mineral')->get();

        // Create layout
        $layout = $this->createLayout(
            Direction::Vertical,
            [
                Constraint::length(7),       // Header
                Constraint::percentage(40),  // Buy section
                Constraint::percentage(40),  // Sell section
                Constraint::length(5),       // Controls
            ],
            $area
        );

        $this->renderTradeHeader($display, $layout[0], $tradingHub, $ship);
        $this->renderBuySection($display, $layout[1], $hubInventories);
        $this->renderSellSection($display, $layout[2], $playerCargo, $hubInventories);
        $this->renderTradeControls($display, $layout[3]);
    }

    public function handleKeyEvent($keyCode): ?string
    {
        // Handle input mode
        if ($this->inputMode) {
            return $this->handleInputModeKey($keyCode);
        }

        // Handle arrow keys
        if ($keyCode instanceof \PhpTui\Term\KeyCode\Up) {
            $this->selectedIndex = max(0, $this->selectedIndex - 1);
            return null;
        }

        if ($keyCode instanceof \PhpTui\Term\KeyCode\Down) {
            $this->handleDownKey();
            return null;
        }

        // Handle tab (switch mode)
        if ($keyCode instanceof \PhpTui\Term\KeyCode\Tab) {
            $this->tradeMode = $this->tradeMode === 'buy' ? 'sell' : 'buy';
            $this->selectedIndex = 0;
            $this->clearMessages();
            return null;
        }

        // Handle enter (enter input mode)
        if ($keyCode instanceof \PhpTui\Term\KeyCode\Enter) {
            $this->inputMode = true;
            $this->inputBuffer = '';
            $this->clearMessages();
            return null;
        }

        // Handle escape
        if ($keyCode instanceof \PhpTui\Term\KeyCode\Esc) {
            return 'back';
        }

        return null;
    }

    public function canNavigate(): bool
    {
        return !$this->inputMode;
    }

    private function handleInputModeKey($keyCode): ?string
    {
        // Handle numeric input
        if ($keyCode instanceof \PhpTui\Term\KeyCode\Char) {
            $this->appendToBuffer($this->inputBuffer, $keyCode->char);
            return null;
        }

        // Handle backspace
        if ($keyCode instanceof \PhpTui\Term\KeyCode\Backspace) {
            $this->backspaceBuffer($this->inputBuffer);
            return null;
        }

        // Handle enter (confirm)
        if ($keyCode instanceof \PhpTui\Term\KeyCode\Enter) {
            $this->executeTransaction();
            $this->inputMode = false;
            $this->inputBuffer = '';
            return null;
        }

        // Handle escape (cancel)
        if ($keyCode instanceof \PhpTui\Term\KeyCode\Esc) {
            $this->inputMode = false;
            $this->inputBuffer = '';
            $this->clearMessages();
            return null;
        }

        return null;
    }

    private function handleDownKey(): void
    {
        $tradingHub = LocationValidator::getTradingHub($this->player);
        if ($tradingHub) {
            $maxIndex = $this->tradeMode === 'buy'
                ? min(8, $tradingHub->inventories()->where('quantity', '>', 0)->count() - 1)
                : min(8, $this->player->activeShip->cargo()->count() - 1);
            $this->selectedIndex = min($maxIndex, $this->selectedIndex + 1);
        }
    }

    private function executeTransaction(): void
    {
        $quantity = $this->parseBufferToInt($this->inputBuffer);

        if ($quantity === null || $quantity <= 0) {
            return;
        }

        $tradingHub = LocationValidator::getTradingHub($this->player);
        if (!$tradingHub) {
            $this->setError("Trading hub not available");
            return;
        }

        if ($this->tradeMode === 'buy') {
            $this->executeBuy($tradingHub, $quantity);
        } else {
            $this->executeSell($tradingHub, $quantity);
        }
    }

    private function executeBuy($tradingHub, int $quantity): void
    {
        $hubInventories = $tradingHub->inventories()->with('mineral')->where('quantity', '>', 0)->get();

        if (!isset($hubInventories[$this->selectedIndex])) {
            $this->setError("Invalid selection");
            return;
        }

        $inventory = $hubInventories[$this->selectedIndex];
        $result = $this->tradeHandler->executeBuy($inventory, $quantity);

        if ($result['success']) {
            $this->setStatus($result['message']);
            $this->reloadPlayer();
        } else {
            $this->setError($result['message']);
        }
    }

    private function executeSell($tradingHub, int $quantity): void
    {
        $ship = $this->player->activeShip;
        $playerCargo = $ship->cargo()->with('mineral')->get();

        if (!isset($playerCargo[$this->selectedIndex])) {
            $this->setError("Invalid selection");
            return;
        }

        $cargo = $playerCargo[$this->selectedIndex];
        $result = $this->tradeHandler->executeSell($cargo, $tradingHub, $quantity);

        if ($result['success']) {
            $this->setStatus($result['message']);
            $this->reloadPlayer();
        } else {
            $this->setError($result['message']);
        }
    }

    private function renderTradeHeader($display, $area, $tradingHub, $ship): void
    {
        $lines = [
            Line::fromString("Trading Hub: " . $tradingHub->name)
                ->style(Style::default()->fg(Color::Magenta)),
            Line::fromString("Credits: " . number_format($this->player->credits, 2))
                ->style(Style::default()->fg(Color::Yellow)),
            Line::fromString("Cargo: {$ship->current_cargo}/{$ship->cargo_hold} units")
                ->style(Style::default()->fg(Color::Cyan)),
        ];

        $block = BlockWidget::default()
            ->borders(Borders::ALL)
            ->borderType(BorderType::Rounded)
            ->title("MINERAL TRADING")
            ->titleStyle(Style::default()->fg(Color::Yellow))
            ->widget(ParagraphWidget::fromLines($lines));

        $display->render($block, $area);
    }

    private function renderBuySection($display, $area, $hubInventories): void
    {
        $items = [];

        if ($hubInventories->isEmpty()) {
            $items[] = Line::fromString("No minerals available for purchase")
                ->style(Style::default()->fg(Color::DarkGray));
        } else {
            foreach ($hubInventories->take(9) as $index => $inventory) {
                $mineral = $inventory->mineral;
                $isSelected = $this->tradeMode === 'buy' && $index === $this->selectedIndex;

                $line = sprintf(
                    "[%d] %-20s Stock: %6s  Price: %8s cr/unit",
                    $index + 1,
                    $mineral->name,
                    number_format($inventory->quantity),
                    number_format($inventory->sell_price, 2)
                );

                $style = Style::default()->fg(Color::White);
                if ($isSelected) {
                    $style = $style->bg(Color::Blue);
                }

                $items[] = Line::fromString($line)->style($style);
            }
        }

        $borderStyle = $this->tradeMode === 'buy'
            ? Style::default()->fg(Color::Green)
            : Style::default()->fg(Color::White);

        $block = BlockWidget::default()
            ->borders(Borders::ALL)
            ->borderStyle($borderStyle)
            ->title("BUY FROM HUB (Hub sells to you)")
            ->titleStyle(Style::default()->fg(Color::Green))
            ->widget(ParagraphWidget::fromLines($items));

        $display->render($block, $area);
    }

    private function renderSellSection($display, $area, $playerCargo, $hubInventories): void
    {
        $items = [];

        if ($playerCargo->isEmpty()) {
            $items[] = Line::fromString("No minerals in cargo")
                ->style(Style::default()->fg(Color::DarkGray));
        } else {
            foreach ($playerCargo->take(9) as $index => $cargo) {
                $mineral = $cargo->mineral;
                $isSelected = $this->tradeMode === 'sell' && $index === $this->selectedIndex;

                // Find hub's buy price
                $hubInventory = $hubInventories->where('mineral_id', $mineral->id)->first();
                $buyPrice = $hubInventory ? $hubInventory->buy_price : $mineral->getMarketValue() * 0.85;

                $line = sprintf(
                    "[%d] %-20s Qty: %6s  Price: %8s cr/unit",
                    $index + 1,
                    $mineral->name,
                    number_format($cargo->quantity),
                    number_format($buyPrice, 2)
                );

                $style = Style::default()->fg(Color::White);
                if ($isSelected) {
                    $style = $style->bg(Color::Blue);
                }

                $items[] = Line::fromString($line)->style($style);
            }
        }

        $borderStyle = $this->tradeMode === 'sell'
            ? Style::default()->fg(Color::Green)
            : Style::default()->fg(Color::White);

        $block = BlockWidget::default()
            ->borders(Borders::ALL)
            ->borderStyle($borderStyle)
            ->title("SELL TO HUB (Hub buys from you)")
            ->titleStyle(Style::default()->fg(Color::Green))
            ->widget(ParagraphWidget::fromLines($items));

        $display->render($block, $area);
    }

    private function renderTradeControls($display, $area): void
    {
        if ($this->inputMode) {
            $controls = [
                "Enter quantity: " . $this->inputBuffer . "_",
                "[0-9] Type number  [Enter] Confirm  [Esc] Cancel"
            ];
        } else {
            $controls = "[↑/↓] Navigate  [Tab] Switch Mode  [Enter] Select  [Esc] Back";
        }

        $controlsWidget = ControlsWidget::create(
            $controls,
            $this->errorMessage,
            $this->statusMessage
        );

        $display->render($controlsWidget, $area);
    }
}
