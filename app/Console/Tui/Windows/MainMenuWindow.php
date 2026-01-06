<?php

namespace App\Console\Tui\Windows;

use App\Models\Player;
use TerminalUI\Core\{Window, Rect};
use TerminalUI\Components\{Label, ListBox, Panel};
use TerminalUI\Styling\{StyleSheet, Color};
use TerminalUI\Layout\Border;
use TerminalUI\Events\{Event, KeyEvent};

/**
 * Main Menu Window - TUI Version
 *
 * Replaces the old ANSI-based main interface with a clean TUI window
 */
class MainMenuWindow extends Window
{
    private Player $player;

    public function __construct(Player $player)
    {
        $this->player = $player;

        // Full-screen window with two-point definition
        parent::__construct(
            Rect::fromPoints(0, 0, 120, 30),
            "Space Wars 3002 - Player Interface"
        );

        $this->setBorder(Border::double());
        $this->setBorderColor(Color::CYAN);
        $this->setClosable(true);

        $this->buildUI();
    }

    /**
     * Build the UI layout
     */
    private function buildUI(): void
    {
        // Header panel (full width, 4 lines tall)
        $this->addHeader();

        // Two-column layout
        $this->addLeftPanel(); // Location and system info
        $this->addRightPanel(); // Ship stats

        // Menu/Controls at bottom
        $this->addMenuPanel();

        // Status bar
        $this->addStatusBar();
    }

    /**
     * Add header with player info
     */
    private function addHeader(): void
    {
        $headerStyle = StyleSheet::create([
            'top' => 0,
            'left' => 0,
            'width' => '100%',
            'height' => 4,
            'border' => 'single',
            'border-color' => Color::BRIGHT_YELLOW,
        ]);

        $header = new Panel($headerStyle);

        // Player name and credits
        $playerInfo = sprintf(
            "Captain: %s | Credits: %s | Level: %d | XP: %s",
            $this->player->call_sign,
            number_format($this->player->credits, 2),
            $this->player->level,
            number_format($this->player->experience)
        );

        $header->add(new Label(
            $playerInfo,
            StyleSheet::create([
                'top' => 1,
                'left' => 2,
                'foreground' => Color::BRIGHT_WHITE,
                'font-weight' => 'bold',
            ])
        ));

        // Location
        $location = sprintf(
            "Location: %s (%s)",
            $this->player->currentLocation->name,
            $this->player->currentLocation->type->value
        );

        $header->add(new Label(
            $location,
            StyleSheet::create([
                'top' => 2,
                'left' => 2,
                'foreground' => Color::BRIGHT_CYAN,
            ])
        ));

        $this->add($header);
    }

    /**
     * Add left panel (location/system info)
     */
    private function addLeftPanel(): void
    {
        $leftStyle = StyleSheet::create([
            'top' => 5,
            'left' => 0,
            'width' => '50%',
            'height' => 16,
            'border' => 'rounded',
            'border-color' => Color::GREEN,
        ]);

        $leftPanel = new Panel($leftStyle);

        // Title
        $leftPanel->add(new Label(
            "Current System",
            StyleSheet::create([
                'top' => 0,
                'left' => 2,
                'foreground' => Color::BRIGHT_GREEN,
                'font-weight' => 'bold',
            ])
        ));

        // Location details
        $location = $this->player->currentLocation;

        $leftPanel->add(new Label(
            sprintf("Type: %s", ucfirst($location->type->value)),
            StyleSheet::create([
                'top' => 2,
                'left' => 2,
                'foreground' => Color::WHITE,
            ])
        ));

        $leftPanel->add(new Label(
            sprintf("Coordinates: (%d, %d)", $location->x, $location->y),
            StyleSheet::create([
                'top' => 3,
                'left' => 2,
                'foreground' => Color::WHITE,
            ])
        ));

        // Available services
        $services = [];
        if ($location->tradingHub && $location->tradingHub->is_active) {
            $services[] = "• Trading Hub";
        }
        if ($location->shipShop && $location->shipShop->is_active) {
            $services[] = "• Ship Shop";
        }
        if ($location->componentShop && $location->componentShop->is_active) {
            $services[] = "• Component Shop";
        }
        if ($location->repairShop && $location->repairShop->is_active) {
            $services[] = "• Repair Shop";
        }
        if ($location->plansShop && $location->plansShop->is_active) {
            $services[] = "• Plans Shop";
        }

        if (!empty($services)) {
            $leftPanel->add(new Label(
                "Available Services:",
                StyleSheet::create([
                    'top' => 5,
                    'left' => 2,
                    'foreground' => Color::BRIGHT_YELLOW,
                    'font-weight' => 'bold',
                ])
            ));

            foreach ($services as $i => $service) {
                $leftPanel->add(new Label(
                    $service,
                    StyleSheet::create([
                        'top' => 6 + $i,
                        'left' => 2,
                        'foreground' => Color::GREEN,
                    ])
                ));
            }
        } else {
            $leftPanel->add(new Label(
                "No services available at this location",
                StyleSheet::create([
                    'top' => 5,
                    'left' => 2,
                    'foreground' => Color::BRIGHT_BLACK,
                ])
            ));
        }

        $this->add($leftPanel);
    }

    /**
     * Add right panel (ship stats)
     */
    private function addRightPanel(): void
    {
        $rightStyle = StyleSheet::create([
            'top' => 5,
            'right' => 0,
            'width' => '48%',
            'height' => 16,
            'border' => 'rounded',
            'border-color' => Color::BLUE,
        ]);

        $rightPanel = new Panel($rightStyle);

        $ship = $this->player->activeShip;

        // Title
        $rightPanel->add(new Label(
            "Ship Status - " . $ship->name,
            StyleSheet::create([
                'top' => 0,
                'left' => 2,
                'foreground' => Color::BRIGHT_BLUE,
                'font-weight' => 'bold',
            ])
        ));

        // Ship class
        $rightPanel->add(new Label(
            sprintf("Class: %s (%s)", $ship->ship->class, $ship->ship->name),
            StyleSheet::create([
                'top' => 2,
                'left' => 2,
                'foreground' => Color::WHITE,
            ])
        ));

        // Hull
        $hullPercent = ($ship->hull / $ship->max_hull) * 100;
        $hullColor = $hullPercent > 70 ? Color::GREEN : ($hullPercent > 30 ? Color::YELLOW : Color::RED);

        $rightPanel->add(new Label(
            sprintf("Hull: %d/%d (%.0f%%)", $ship->hull, $ship->max_hull, $hullPercent),
            StyleSheet::create([
                'top' => 4,
                'left' => 2,
                'foreground' => $hullColor,
            ])
        ));

        // Fuel
        $ship->regenerateFuel();
        $fuelPercent = ($ship->current_fuel / $ship->max_fuel) * 100;
        $fuelColor = $fuelPercent > 70 ? Color::GREEN : ($fuelPercent > 30 ? Color::YELLOW : Color::RED);

        $rightPanel->add(new Label(
            sprintf("Fuel: %.1f/%.1f (%.0f%%)", $ship->current_fuel, $ship->max_fuel, $fuelPercent),
            StyleSheet::create([
                'top' => 5,
                'left' => 2,
                'foreground' => $fuelColor,
            ])
        ));

        // Cargo
        $cargoPercent = $ship->cargo_hold > 0 ? ($ship->current_cargo / $ship->cargo_hold) * 100 : 0;

        $rightPanel->add(new Label(
            sprintf("Cargo: %d/%d (%.0f%% full)", $ship->current_cargo, $ship->cargo_hold, $cargoPercent),
            StyleSheet::create([
                'top' => 6,
                'left' => 2,
                'foreground' => Color::CYAN,
            ])
        ));

        // Combat stats
        $rightPanel->add(new Label(
            sprintf("Weapons: %d", $ship->weapons),
            StyleSheet::create([
                'top' => 8,
                'left' => 2,
                'foreground' => Color::WHITE,
            ])
        ));

        $rightPanel->add(new Label(
            sprintf("Sensors: %d", $ship->sensors),
            StyleSheet::create([
                'top' => 9,
                'left' => 2,
                'foreground' => Color::WHITE,
            ])
        ));

        $rightPanel->add(new Label(
            sprintf("Warp Drive: %d", $ship->warp_drive),
            StyleSheet::create([
                'top' => 10,
                'left' => 2,
                'foreground' => Color::WHITE,
            ])
        ));

        $rightPanel->add(new Label(
            sprintf("Speed: %d", $ship->ship->speed),
            StyleSheet::create([
                'top' => 11,
                'left' => 2,
                'foreground' => Color::WHITE,
            ])
        ));

        $this->add($rightPanel);
    }

    /**
     * Add menu panel with options
     */
    private function addMenuPanel(): void
    {
        $menuStyle = StyleSheet::create([
            'top' => 22,
            'left' => 0,
            'width' => '100%',
            'height' => 6,
            'border' => 'thick',
            'border-color' => Color::MAGENTA,
        ]);

        $menu = new Panel($menuStyle);

        // Menu title
        $menu->add(new Label(
            "Available Commands",
            StyleSheet::create([
                'top' => 0,
                'left' => 2,
                'foreground' => Color::BRIGHT_MAGENTA,
                'font-weight' => 'bold',
            ])
        ));

        // Commands
        $commands = [
            "[S] Ship Info  [C] Cargo  [U] Upgrades",
            "[T] Trade  [W] Travel  [B] Buy Ship",
            "[P] Components  [L] Plans  [M] Repair",
        ];

        foreach ($commands as $i => $cmd) {
            $menu->add(new Label(
                $cmd,
                StyleSheet::create([
                    'top' => 1 + $i,
                    'left' => 2,
                    'foreground' => Color::WHITE,
                ])
            ));
        }

        $this->add($menu);
    }

    /**
     * Add status bar
     */
    private function addStatusBar(): void
    {
        $statusStyle = StyleSheet::create([
            'bottom' => 0,
            'left' => 0,
            'width' => '100%',
            'height' => 1,
            'background' => Color::BRIGHT_BLACK,
            'foreground' => Color::WHITE,
        ]);

        $status = new Label(
            "Press R to refresh | Press Q or ESC to exit",
            $statusStyle
        );

        $this->add($status);
    }

    /**
     * Handle key events
     */
    public function handleEvent(Event $event): bool
    {
        // Let parent handle first (ESC to close, etc.)
        if (parent::handleEvent($event)) {
            return true;
        }

        // Custom key handling
        if ($event instanceof KeyEvent) {
            return match(strtolower($event->key)) {
                'r' => $this->refresh(),
                's' => $this->showShipInfo(),
                'c' => $this->showCargo(),
                'u' => $this->showUpgrades(),
                't' => $this->showTrade(),
                'w' => $this->showTravel(),
                'b' => $this->showShipShop(),
                'p' => $this->showComponents(),
                'l' => $this->showPlans(),
                'm' => $this->showRepair(),
                'q' => $this->close(),
                default => false,
            };
        }

        return false;
    }

    /**
     * Refresh player data and UI
     */
    private function refresh(): bool
    {
        $this->player->refresh();
        $this->player->load([
            'currentLocation.children',
            'currentLocation.parent',
            'activeShip.ship',
            'activeShip.cargo.mineral'
        ]);

        // Rebuild UI
        $this->removeAll();
        $this->buildUI();
        $this->invalidate();

        return true;
    }

    // Placeholder methods for menu options
    // These will open new windows or trigger handlers
    private function showShipInfo(): bool
    {
        // TODO: Open ship info window
        return false;
    }

    private function showCargo(): bool
    {
        // TODO: Open cargo window
        return false;
    }

    private function showUpgrades(): bool
    {
        // TODO: Open upgrades window
        return false;
    }

    private function showTrade(): bool
    {
        // TODO: Open trading window
        return false;
    }

    private function showTravel(): bool
    {
        // TODO: Open travel window
        return false;
    }

    private function showShipShop(): bool
    {
        // TODO: Open ship shop window
        return false;
    }

    private function showComponents(): bool
    {
        // TODO: Open component shop window
        return false;
    }

    private function showPlans(): bool
    {
        // TODO: Open plans shop window
        return false;
    }

    private function showRepair(): bool
    {
        // TODO: Open repair shop window
        return false;
    }
}
