<?php

namespace App\Enums\Vendor;

enum VendorArchetype: string
{
    // Shipyard
    case CORPORATE_SHIPBUILDER = 'corporate_shipbuilder';
    case LUXURY_SHIPWRIGHT = 'luxury_shipwright';
    case MILITARY_CONTRACTOR = 'military_contractor';
    case INDEPENDENT_BUILDER = 'independent_builder';

    // Salvage Yard
    case HONEST_SCRAPPER = 'honest_scrapper';
    case GREEDY_JUNK_DEALER = 'greedy_junk_dealer';
    case BLACK_MARKET_SALVAGER = 'black_market_salvager';
    case BATTLEFIELD_SCAVENGER = 'battlefield_scavenger';

    // Mineral Exchange (trading_hub)
    case COMMODITY_BROKER = 'commodity_broker';
    case MARKET_SHARK = 'market_shark';
    case INDUSTRIAL_CONTRACT_BUYER = 'industrial_contract_buyer';
    case SPECULATOR = 'speculator';

    // Repair Yard
    case MASTER_ENGINEER = 'master_engineer';
    case PATCHWORK_MECHANIC = 'patchwork_mechanic';
    case CORPORATE_MAINTENANCE = 'corporate_maintenance';
    case BATTLEFIELD_TECHNICIAN = 'battlefield_technician';

    // Bartender
    case FRIENDLY_LISTENER = 'friendly_listener';
    case CYNICAL_VETERAN = 'cynical_veteran';
    case GOSSIP_MILL = 'gossip_mill';
    case STATION_FIXER = 'station_fixer';

    // Information Broker
    case ANALYST = 'analyst';
    case SHADOW_BROKER = 'shadow_broker';
    case ARCHIVIST = 'archivist';
    case RUMOUR_MERCHANT = 'rumour_merchant';

    public function label(): string
    {
        return match ($this) {
            self::CORPORATE_SHIPBUILDER    => 'Corporate Shipbuilder',
            self::LUXURY_SHIPWRIGHT        => 'Luxury Shipwright',
            self::MILITARY_CONTRACTOR      => 'Military Contractor',
            self::INDEPENDENT_BUILDER      => 'Independent Builder',
            self::HONEST_SCRAPPER          => 'Honest Scrapper',
            self::GREEDY_JUNK_DEALER       => 'Greedy Junk Dealer',
            self::BLACK_MARKET_SALVAGER    => 'Black Market Salvager',
            self::BATTLEFIELD_SCAVENGER    => 'Battlefield Scavenger',
            self::COMMODITY_BROKER         => 'Commodity Broker',
            self::MARKET_SHARK             => 'Market Shark',
            self::INDUSTRIAL_CONTRACT_BUYER => 'Industrial Contract Buyer',
            self::SPECULATOR               => 'Speculator',
            self::MASTER_ENGINEER          => 'Master Engineer',
            self::PATCHWORK_MECHANIC       => 'Patchwork Mechanic',
            self::CORPORATE_MAINTENANCE    => 'Corporate Maintenance',
            self::BATTLEFIELD_TECHNICIAN   => 'Battlefield Technician',
            self::FRIENDLY_LISTENER        => 'Friendly Listener',
            self::CYNICAL_VETERAN          => 'Cynical Veteran',
            self::GOSSIP_MILL              => 'Gossip Mill',
            self::STATION_FIXER            => 'Station Fixer',
            self::ANALYST                  => 'Analyst',
            self::SHADOW_BROKER            => 'Shadow Broker',
            self::ARCHIVIST                => 'Archivist',
            self::RUMOUR_MERCHANT          => 'Rumour Merchant',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::CORPORATE_SHIPBUILDER    => 'Formal and lawful. Prices are firm, quality is guaranteed.',
            self::LUXURY_SHIPWRIGHT        => 'Pride above profit. The finest hulls, no haggling tolerated.',
            self::MILITARY_CONTRACTOR      => 'Government-grade builds. Strict compliance, no deviation from the price list.',
            self::INDEPENDENT_BUILDER      => 'A craftsperson first. Fair prices, flexible, passionate about their work.',
            self::HONEST_SCRAPPER          => 'What you see is what you get. Discloses defects, fair pricing.',
            self::GREEDY_JUNK_DEALER       => 'Everything has a price, and the price is high. Defects optional.',
            self::BLACK_MARKET_SALVAGER    => 'No questions asked. No names either. Paranoid by necessity.',
            self::BATTLEFIELD_SCAVENGER    => 'Military surplus, crash wreckage, and everything in between. High risk, high reward.',
            self::COMMODITY_BROKER         => 'Professional and balanced. Fair margins, consistent service.',
            self::MARKET_SHARK             => 'Watches supply chains like a hawk. Will squeeze every credit from a price spike.',
            self::INDUSTRIAL_CONTRACT_BUYER => 'Bulk buyer for corporate clients. Lawful, reliable, difficult to negotiate with.',
            self::SPECULATOR               => 'Bets on market swings. Volatile pricing, impatient temperament.',
            self::MASTER_ENGINEER          => 'Uncompromising quality. No shortcuts, no excuses, no discounts.',
            self::PATCHWORK_MECHANIC       => 'Gets it running. Quality is flexible. Price is very flexible.',
            self::CORPORATE_MAINTENANCE    => 'Standardised service at standardised prices. Predictable if not cheap.',
            self::BATTLEFIELD_TECHNICIAN   => 'Rapid repairs under pressure. Combat-zone experience, informal manner.',
            self::FRIENDLY_LISTENER        => 'Warm and genuinely interested. Will share what they know if you share back.',
            self::CYNICAL_VETERAN          => 'Seen it all. Hard to impress, harder to fool, slow to trust.',
            self::GOSSIP_MILL              => 'Cannot help but talk. A fountain of information — accurate or otherwise.',
            self::STATION_FIXER            => 'Knows everyone and everything. Operates quietly in the grey areas.',
            self::ANALYST                  => 'Data-driven and precise. Prices intelligence carefully, methodical.',
            self::SHADOW_BROKER            => 'Deals in dangerous information. Paranoid, criminal, and extremely perceptive.',
            self::ARCHIVIST                => 'Catalogues everything. Honest, formal, and fascinated by rare knowledge.',
            self::RUMOUR_MERCHANT          => 'Charm first, accuracy second. Sells stories as readily as facts.',
        };
    }

    public function baseMarkup(): float
    {
        return match ($this) {
            self::CORPORATE_SHIPBUILDER    => 0.10,
            self::LUXURY_SHIPWRIGHT        => 0.18,
            self::MILITARY_CONTRACTOR      => 0.08,
            self::INDEPENDENT_BUILDER      => 0.02,
            self::HONEST_SCRAPPER          => 0.05,
            self::GREEDY_JUNK_DEALER       => 0.22,
            self::BLACK_MARKET_SALVAGER    => 0.25,
            self::BATTLEFIELD_SCAVENGER    => 0.15,
            self::COMMODITY_BROKER         => 0.06,
            self::MARKET_SHARK             => 0.20,
            self::INDUSTRIAL_CONTRACT_BUYER => 0.07,
            self::SPECULATOR               => 0.12,
            self::MASTER_ENGINEER          => 0.12,
            self::PATCHWORK_MECHANIC       => 0.03,
            self::CORPORATE_MAINTENANCE    => 0.09,
            self::BATTLEFIELD_TECHNICIAN   => 0.06,
            self::FRIENDLY_LISTENER        => 0.00,
            self::CYNICAL_VETERAN          => 0.05,
            self::GOSSIP_MILL              => 0.00,
            self::STATION_FIXER            => 0.10,
            self::ANALYST                  => 0.12,
            self::SHADOW_BROKER            => 0.20,
            self::ARCHIVIST                => 0.08,
            self::RUMOUR_MERCHANT          => 0.15,
        };
    }

    public function maxGoodwillBonus(): float
    {
        return match ($this) {
            self::CORPORATE_SHIPBUILDER    => 0.06,
            self::LUXURY_SHIPWRIGHT        => 0.04,
            self::MILITARY_CONTRACTOR      => 0.03,
            self::INDEPENDENT_BUILDER      => 0.10,
            self::HONEST_SCRAPPER          => 0.08,
            self::GREEDY_JUNK_DEALER       => 0.06,
            self::BLACK_MARKET_SALVAGER    => 0.12,
            self::BATTLEFIELD_SCAVENGER    => 0.10,
            self::COMMODITY_BROKER         => 0.07,
            self::MARKET_SHARK             => 0.05,
            self::INDUSTRIAL_CONTRACT_BUYER => 0.05,
            self::SPECULATOR               => 0.08,
            self::MASTER_ENGINEER          => 0.05,
            self::PATCHWORK_MECHANIC       => 0.12,
            self::CORPORATE_MAINTENANCE    => 0.03,
            self::BATTLEFIELD_TECHNICIAN   => 0.10,
            self::FRIENDLY_LISTENER        => 0.10,
            self::CYNICAL_VETERAN          => 0.08,
            self::GOSSIP_MILL              => 0.08,
            self::STATION_FIXER            => 0.15,
            self::ANALYST                  => 0.08,
            self::SHADOW_BROKER            => 0.14,
            self::ARCHIVIST                => 0.06,
            self::RUMOUR_MERCHANT          => 0.10,
        };
    }

    /**
     * Canonical trait vector for this archetype.
     * See ADR 0002 for full framework and rationale.
     *
     * Trait order: greed, price_flexibility, bargaining_skill, patience,
     * technical_knowledge, opportunism, lawfulness, criminality, scrupulousness,
     * perceptiveness, risk_tolerance, honesty, charm, empathy, curiosity,
     * loyalty, grudge_factor, gossip_factor, paranoia, pride_in_work,
     * ego_drive, fear_threshold, formality, verbosity
     */
    public function traitVector(): array
    {
        return match ($this) {
            self::CORPORATE_SHIPBUILDER => [
                'greed' => 0.60, 'price_flexibility' => 0.20, 'bargaining_skill' => 0.70,
                'patience' => 0.45, 'technical_knowledge' => 0.85, 'opportunism' => 0.60,
                'lawfulness' => 0.85, 'criminality' => 0.10, 'scrupulousness' => 0.80,
                'perceptiveness' => 0.65, 'risk_tolerance' => 0.25,
                'honesty' => 0.65, 'charm' => 0.70, 'empathy' => 0.45, 'curiosity' => 0.55,
                'loyalty' => 0.45, 'grudge_factor' => 0.50, 'gossip_factor' => 0.20,
                'paranoia' => 0.30, 'pride_in_work' => 0.80, 'ego_drive' => 0.70,
                'fear_threshold' => 0.65, 'formality' => 0.90, 'verbosity' => 0.50,
            ],
            self::LUXURY_SHIPWRIGHT => [
                'greed' => 0.70, 'price_flexibility' => 0.10, 'bargaining_skill' => 0.60,
                'patience' => 0.35, 'technical_knowledge' => 0.95, 'opportunism' => 0.45,
                'lawfulness' => 0.75, 'criminality' => 0.10, 'scrupulousness' => 0.90,
                'perceptiveness' => 0.70, 'risk_tolerance' => 0.20,
                'honesty' => 0.75, 'charm' => 0.65, 'empathy' => 0.40, 'curiosity' => 0.70,
                'loyalty' => 0.50, 'grudge_factor' => 0.65, 'gossip_factor' => 0.25,
                'paranoia' => 0.35, 'pride_in_work' => 0.98, 'ego_drive' => 0.85,
                'fear_threshold' => 0.70, 'formality' => 0.85, 'verbosity' => 0.65,
            ],
            self::MILITARY_CONTRACTOR => [
                'greed' => 0.55, 'price_flexibility' => 0.10, 'bargaining_skill' => 0.80,
                'patience' => 0.40, 'technical_knowledge' => 0.85, 'opportunism' => 0.40,
                'lawfulness' => 0.95, 'criminality' => 0.02, 'scrupulousness' => 0.85,
                'perceptiveness' => 0.75, 'risk_tolerance' => 0.25,
                'honesty' => 0.70, 'charm' => 0.50, 'empathy' => 0.35, 'curiosity' => 0.45,
                'loyalty' => 0.55, 'grudge_factor' => 0.60, 'gossip_factor' => 0.15,
                'paranoia' => 0.45, 'pride_in_work' => 0.75, 'ego_drive' => 0.65,
                'fear_threshold' => 0.80, 'formality' => 0.85, 'verbosity' => 0.30,
            ],
            self::INDEPENDENT_BUILDER => [
                'greed' => 0.30, 'price_flexibility' => 0.60, 'bargaining_skill' => 0.40,
                'patience' => 0.75, 'technical_knowledge' => 0.90, 'opportunism' => 0.25,
                'lawfulness' => 0.65, 'criminality' => 0.20, 'scrupulousness' => 0.85,
                'perceptiveness' => 0.60, 'risk_tolerance' => 0.55,
                'honesty' => 0.80, 'charm' => 0.50, 'empathy' => 0.55, 'curiosity' => 0.80,
                'loyalty' => 0.70, 'grudge_factor' => 0.35, 'gossip_factor' => 0.35,
                'paranoia' => 0.20, 'pride_in_work' => 0.90, 'ego_drive' => 0.50,
                'fear_threshold' => 0.55, 'formality' => 0.30, 'verbosity' => 0.55,
            ],
            self::HONEST_SCRAPPER => [
                'greed' => 0.35, 'price_flexibility' => 0.65, 'bargaining_skill' => 0.50,
                'patience' => 0.65, 'technical_knowledge' => 0.60, 'opportunism' => 0.30,
                'lawfulness' => 0.75, 'criminality' => 0.15, 'scrupulousness' => 0.80,
                'perceptiveness' => 0.65, 'risk_tolerance' => 0.55,
                'honesty' => 0.85, 'charm' => 0.55, 'empathy' => 0.60, 'curiosity' => 0.50,
                'loyalty' => 0.65, 'grudge_factor' => 0.25, 'gossip_factor' => 0.40,
                'paranoia' => 0.20, 'pride_in_work' => 0.60, 'ego_drive' => 0.35,
                'fear_threshold' => 0.55, 'formality' => 0.35, 'verbosity' => 0.50,
            ],
            self::GREEDY_JUNK_DEALER => [
                'greed' => 0.80, 'price_flexibility' => 0.40, 'bargaining_skill' => 0.70,
                'patience' => 0.45, 'technical_knowledge' => 0.50, 'opportunism' => 0.85,
                'lawfulness' => 0.40, 'criminality' => 0.55, 'scrupulousness' => 0.20,
                'perceptiveness' => 0.70, 'risk_tolerance' => 0.65,
                'honesty' => 0.20, 'charm' => 0.60, 'empathy' => 0.15, 'curiosity' => 0.35,
                'loyalty' => 0.30, 'grudge_factor' => 0.65, 'gossip_factor' => 0.60,
                'paranoia' => 0.50, 'pride_in_work' => 0.25, 'ego_drive' => 0.70,
                'fear_threshold' => 0.45, 'formality' => 0.30, 'verbosity' => 0.45,
            ],
            self::BLACK_MARKET_SALVAGER => [
                'greed' => 0.75, 'price_flexibility' => 0.45, 'bargaining_skill' => 0.80,
                'patience' => 0.50, 'technical_knowledge' => 0.55, 'opportunism' => 0.80,
                'lawfulness' => 0.10, 'criminality' => 0.90, 'scrupulousness' => 0.15,
                'perceptiveness' => 0.85, 'risk_tolerance' => 0.90,
                'honesty' => 0.15, 'charm' => 0.40, 'empathy' => 0.10, 'curiosity' => 0.30,
                'loyalty' => 0.20, 'grudge_factor' => 0.80, 'gossip_factor' => 0.45,
                'paranoia' => 0.90, 'pride_in_work' => 0.20, 'ego_drive' => 0.60,
                'fear_threshold' => 0.20, 'formality' => 0.15, 'verbosity' => 0.25,
            ],
            self::BATTLEFIELD_SCAVENGER => [
                'greed' => 0.60, 'price_flexibility' => 0.55, 'bargaining_skill' => 0.60,
                'patience' => 0.55, 'technical_knowledge' => 0.70, 'opportunism' => 0.65,
                'lawfulness' => 0.35, 'criminality' => 0.60, 'scrupulousness' => 0.35,
                'perceptiveness' => 0.75, 'risk_tolerance' => 0.95,
                'honesty' => 0.40, 'charm' => 0.45, 'empathy' => 0.25, 'curiosity' => 0.60,
                'loyalty' => 0.35, 'grudge_factor' => 0.55, 'gossip_factor' => 0.50,
                'paranoia' => 0.65, 'pride_in_work' => 0.40, 'ego_drive' => 0.65,
                'fear_threshold' => 0.30, 'formality' => 0.20, 'verbosity' => 0.40,
            ],
            self::COMMODITY_BROKER => [
                'greed' => 0.50, 'price_flexibility' => 0.50, 'bargaining_skill' => 0.60,
                'patience' => 0.55, 'technical_knowledge' => 0.65, 'opportunism' => 0.55,
                'lawfulness' => 0.70, 'criminality' => 0.20, 'scrupulousness' => 0.65,
                'perceptiveness' => 0.65, 'risk_tolerance' => 0.50,
                'honesty' => 0.65, 'charm' => 0.60, 'empathy' => 0.50, 'curiosity' => 0.55,
                'loyalty' => 0.55, 'grudge_factor' => 0.40, 'gossip_factor' => 0.35,
                'paranoia' => 0.30, 'pride_in_work' => 0.55, 'ego_drive' => 0.55,
                'fear_threshold' => 0.60, 'formality' => 0.60, 'verbosity' => 0.50,
            ],
            self::MARKET_SHARK => [
                'greed' => 0.90, 'price_flexibility' => 0.25, 'bargaining_skill' => 0.85,
                'patience' => 0.30, 'technical_knowledge' => 0.60, 'opportunism' => 0.95,
                'lawfulness' => 0.45, 'criminality' => 0.45, 'scrupulousness' => 0.35,
                'perceptiveness' => 0.80, 'risk_tolerance' => 0.70,
                'honesty' => 0.15, 'charm' => 0.55, 'empathy' => 0.10, 'curiosity' => 0.40,
                'loyalty' => 0.20, 'grudge_factor' => 0.70, 'gossip_factor' => 0.30,
                'paranoia' => 0.55, 'pride_in_work' => 0.35, 'ego_drive' => 0.85,
                'fear_threshold' => 0.65, 'formality' => 0.55, 'verbosity' => 0.30,
            ],
            self::INDUSTRIAL_CONTRACT_BUYER => [
                'greed' => 0.55, 'price_flexibility' => 0.25, 'bargaining_skill' => 0.75,
                'patience' => 0.60, 'technical_knowledge' => 0.75, 'opportunism' => 0.35,
                'lawfulness' => 0.85, 'criminality' => 0.10, 'scrupulousness' => 0.70,
                'perceptiveness' => 0.70, 'risk_tolerance' => 0.35,
                'honesty' => 0.70, 'charm' => 0.55, 'empathy' => 0.45, 'curiosity' => 0.60,
                'loyalty' => 0.60, 'grudge_factor' => 0.45, 'gossip_factor' => 0.20,
                'paranoia' => 0.35, 'pride_in_work' => 0.55, 'ego_drive' => 0.60,
                'fear_threshold' => 0.70, 'formality' => 0.75, 'verbosity' => 0.40,
            ],
            self::SPECULATOR => [
                'greed' => 0.70, 'price_flexibility' => 0.70, 'bargaining_skill' => 0.55,
                'patience' => 0.20, 'technical_knowledge' => 0.50, 'opportunism' => 0.90,
                'lawfulness' => 0.55, 'criminality' => 0.35, 'scrupulousness' => 0.40,
                'perceptiveness' => 0.65, 'risk_tolerance' => 0.90,
                'honesty' => 0.45, 'charm' => 0.60, 'empathy' => 0.30, 'curiosity' => 0.65,
                'loyalty' => 0.30, 'grudge_factor' => 0.45, 'gossip_factor' => 0.55,
                'paranoia' => 0.40, 'pride_in_work' => 0.35, 'ego_drive' => 0.70,
                'fear_threshold' => 0.50, 'formality' => 0.40, 'verbosity' => 0.55,
            ],
            self::MASTER_ENGINEER => [
                'greed' => 0.45, 'price_flexibility' => 0.35, 'bargaining_skill' => 0.40,
                'patience' => 0.70, 'technical_knowledge' => 0.98, 'opportunism' => 0.25,
                'lawfulness' => 0.80, 'criminality' => 0.05, 'scrupulousness' => 0.95,
                'perceptiveness' => 0.75, 'risk_tolerance' => 0.30,
                'honesty' => 0.90, 'charm' => 0.50, 'empathy' => 0.55, 'curiosity' => 0.80,
                'loyalty' => 0.65, 'grudge_factor' => 0.35, 'gossip_factor' => 0.25,
                'paranoia' => 0.20, 'pride_in_work' => 0.98, 'ego_drive' => 0.60,
                'fear_threshold' => 0.65, 'formality' => 0.55, 'verbosity' => 0.55,
            ],
            self::PATCHWORK_MECHANIC => [
                'greed' => 0.40, 'price_flexibility' => 0.75, 'bargaining_skill' => 0.50,
                'patience' => 0.60, 'technical_knowledge' => 0.55, 'opportunism' => 0.40,
                'lawfulness' => 0.55, 'criminality' => 0.30, 'scrupulousness' => 0.30,
                'perceptiveness' => 0.50, 'risk_tolerance' => 0.70,
                'honesty' => 0.50, 'charm' => 0.50, 'empathy' => 0.55, 'curiosity' => 0.45,
                'loyalty' => 0.55, 'grudge_factor' => 0.30, 'gossip_factor' => 0.50,
                'paranoia' => 0.25, 'pride_in_work' => 0.25, 'ego_drive' => 0.35,
                'fear_threshold' => 0.50, 'formality' => 0.25, 'verbosity' => 0.55,
            ],
            self::CORPORATE_MAINTENANCE => [
                'greed' => 0.60, 'price_flexibility' => 0.15, 'bargaining_skill' => 0.65,
                'patience' => 0.45, 'technical_knowledge' => 0.80, 'opportunism' => 0.50,
                'lawfulness' => 0.90, 'criminality' => 0.05, 'scrupulousness' => 0.75,
                'perceptiveness' => 0.65, 'risk_tolerance' => 0.25,
                'honesty' => 0.70, 'charm' => 0.60, 'empathy' => 0.40, 'curiosity' => 0.45,
                'loyalty' => 0.40, 'grudge_factor' => 0.45, 'gossip_factor' => 0.20,
                'paranoia' => 0.30, 'pride_in_work' => 0.65, 'ego_drive' => 0.55,
                'fear_threshold' => 0.65, 'formality' => 0.85, 'verbosity' => 0.40,
            ],
            self::BATTLEFIELD_TECHNICIAN => [
                'greed' => 0.45, 'price_flexibility' => 0.60, 'bargaining_skill' => 0.55,
                'patience' => 0.50, 'technical_knowledge' => 0.80, 'opportunism' => 0.45,
                'lawfulness' => 0.45, 'criminality' => 0.35, 'scrupulousness' => 0.55,
                'perceptiveness' => 0.70, 'risk_tolerance' => 0.85,
                'honesty' => 0.65, 'charm' => 0.50, 'empathy' => 0.60, 'curiosity' => 0.60,
                'loyalty' => 0.60, 'grudge_factor' => 0.40, 'gossip_factor' => 0.45,
                'paranoia' => 0.55, 'pride_in_work' => 0.65, 'ego_drive' => 0.50,
                'fear_threshold' => 0.35, 'formality' => 0.20, 'verbosity' => 0.40,
            ],
            self::FRIENDLY_LISTENER => [
                'greed' => 0.25, 'price_flexibility' => 0.70, 'bargaining_skill' => 0.35,
                'patience' => 0.80, 'technical_knowledge' => 0.25, 'opportunism' => 0.20,
                'lawfulness' => 0.65, 'criminality' => 0.15, 'scrupulousness' => 0.60,
                'perceptiveness' => 0.60, 'risk_tolerance' => 0.50,
                'honesty' => 0.75, 'charm' => 0.90, 'empathy' => 0.90, 'curiosity' => 0.70,
                'loyalty' => 0.80, 'grudge_factor' => 0.15, 'gossip_factor' => 0.65,
                'paranoia' => 0.10, 'pride_in_work' => 0.55, 'ego_drive' => 0.35,
                'fear_threshold' => 0.45, 'formality' => 0.45, 'verbosity' => 0.80,
            ],
            self::CYNICAL_VETERAN => [
                'greed' => 0.40, 'price_flexibility' => 0.45, 'bargaining_skill' => 0.55,
                'patience' => 0.65, 'technical_knowledge' => 0.40, 'opportunism' => 0.40,
                'lawfulness' => 0.55, 'criminality' => 0.35, 'scrupulousness' => 0.55,
                'perceptiveness' => 0.80, 'risk_tolerance' => 0.55,
                'honesty' => 0.60, 'charm' => 0.50, 'empathy' => 0.30, 'curiosity' => 0.50,
                'loyalty' => 0.55, 'grudge_factor' => 0.70, 'gossip_factor' => 0.25,
                'paranoia' => 0.75, 'pride_in_work' => 0.50, 'ego_drive' => 0.55,
                'fear_threshold' => 0.70, 'formality' => 0.40, 'verbosity' => 0.35,
            ],
            self::GOSSIP_MILL => [
                'greed' => 0.35, 'price_flexibility' => 0.60, 'bargaining_skill' => 0.40,
                'patience' => 0.75, 'technical_knowledge' => 0.30, 'opportunism' => 0.35,
                'lawfulness' => 0.60, 'criminality' => 0.25, 'scrupulousness' => 0.50,
                'perceptiveness' => 0.65, 'risk_tolerance' => 0.45,
                'honesty' => 0.55, 'charm' => 0.85, 'empathy' => 0.70, 'curiosity' => 0.85,
                'loyalty' => 0.65, 'grudge_factor' => 0.20, 'gossip_factor' => 0.95,
                'paranoia' => 0.05, 'pride_in_work' => 0.45, 'ego_drive' => 0.50,
                'fear_threshold' => 0.35, 'formality' => 0.40, 'verbosity' => 0.95,
            ],
            self::STATION_FIXER => [
                'greed' => 0.60, 'price_flexibility' => 0.50, 'bargaining_skill' => 0.70,
                'patience' => 0.65, 'technical_knowledge' => 0.45, 'opportunism' => 0.65,
                'lawfulness' => 0.30, 'criminality' => 0.65, 'scrupulousness' => 0.40,
                'perceptiveness' => 0.80, 'risk_tolerance' => 0.70,
                'honesty' => 0.35, 'charm' => 0.75, 'empathy' => 0.55, 'curiosity' => 0.65,
                'loyalty' => 0.50, 'grudge_factor' => 0.60, 'gossip_factor' => 0.55,
                'paranoia' => 0.75, 'pride_in_work' => 0.40, 'ego_drive' => 0.70,
                'fear_threshold' => 0.30, 'formality' => 0.35, 'verbosity' => 0.55,
            ],
            self::ANALYST => [
                'greed' => 0.65, 'price_flexibility' => 0.35, 'bargaining_skill' => 0.70,
                'patience' => 0.70, 'technical_knowledge' => 0.85, 'opportunism' => 0.55,
                'lawfulness' => 0.65, 'criminality' => 0.25, 'scrupulousness' => 0.70,
                'perceptiveness' => 0.90, 'risk_tolerance' => 0.45,
                'honesty' => 0.60, 'charm' => 0.50, 'empathy' => 0.40, 'curiosity' => 0.85,
                'loyalty' => 0.55, 'grudge_factor' => 0.45, 'gossip_factor' => 0.40,
                'paranoia' => 0.55, 'pride_in_work' => 0.70, 'ego_drive' => 0.60,
                'fear_threshold' => 0.60, 'formality' => 0.70, 'verbosity' => 0.55,
            ],
            self::SHADOW_BROKER => [
                'greed' => 0.75, 'price_flexibility' => 0.30, 'bargaining_skill' => 0.80,
                'patience' => 0.60, 'technical_knowledge' => 0.70, 'opportunism' => 0.75,
                'lawfulness' => 0.15, 'criminality' => 0.85, 'scrupulousness' => 0.30,
                'perceptiveness' => 0.90, 'risk_tolerance' => 0.75,
                'honesty' => 0.20, 'charm' => 0.45, 'empathy' => 0.15, 'curiosity' => 0.70,
                'loyalty' => 0.25, 'grudge_factor' => 0.85, 'gossip_factor' => 0.35,
                'paranoia' => 0.95, 'pride_in_work' => 0.55, 'ego_drive' => 0.65,
                'fear_threshold' => 0.25, 'formality' => 0.35, 'verbosity' => 0.30,
            ],
            self::ARCHIVIST => [
                'greed' => 0.40, 'price_flexibility' => 0.55, 'bargaining_skill' => 0.45,
                'patience' => 0.80, 'technical_knowledge' => 0.90, 'opportunism' => 0.30,
                'lawfulness' => 0.80, 'criminality' => 0.10, 'scrupulousness' => 0.85,
                'perceptiveness' => 0.75, 'risk_tolerance' => 0.35,
                'honesty' => 0.85, 'charm' => 0.55, 'empathy' => 0.60, 'curiosity' => 0.95,
                'loyalty' => 0.65, 'grudge_factor' => 0.25, 'gossip_factor' => 0.45,
                'paranoia' => 0.40, 'pride_in_work' => 0.80, 'ego_drive' => 0.45,
                'fear_threshold' => 0.60, 'formality' => 0.80, 'verbosity' => 0.75,
            ],
            self::RUMOUR_MERCHANT => [
                'greed' => 0.70, 'price_flexibility' => 0.55, 'bargaining_skill' => 0.65,
                'patience' => 0.55, 'technical_knowledge' => 0.40, 'opportunism' => 0.70,
                'lawfulness' => 0.50, 'criminality' => 0.45, 'scrupulousness' => 0.35,
                'perceptiveness' => 0.65, 'risk_tolerance' => 0.60,
                'honesty' => 0.30, 'charm' => 0.85, 'empathy' => 0.50, 'curiosity' => 0.80,
                'loyalty' => 0.40, 'grudge_factor' => 0.40, 'gossip_factor' => 0.90,
                'paranoia' => 0.35, 'pride_in_work' => 0.35, 'ego_drive' => 0.65,
                'fear_threshold' => 0.45, 'formality' => 0.45, 'verbosity' => 0.85,
            ],
        };
    }

    public function serviceType(): string
    {
        return match ($this) {
            self::CORPORATE_SHIPBUILDER, self::LUXURY_SHIPWRIGHT,
            self::MILITARY_CONTRACTOR, self::INDEPENDENT_BUILDER     => 'shipyard',

            self::HONEST_SCRAPPER, self::GREEDY_JUNK_DEALER,
            self::BLACK_MARKET_SALVAGER, self::BATTLEFIELD_SCAVENGER => 'salvage_yard',

            self::COMMODITY_BROKER, self::MARKET_SHARK,
            self::INDUSTRIAL_CONTRACT_BUYER, self::SPECULATOR         => 'trading_hub',

            self::MASTER_ENGINEER, self::PATCHWORK_MECHANIC,
            self::CORPORATE_MAINTENANCE, self::BATTLEFIELD_TECHNICIAN => 'repair_yard',

            self::FRIENDLY_LISTENER, self::CYNICAL_VETERAN,
            self::GOSSIP_MILL, self::STATION_FIXER                   => 'bartender',

            self::ANALYST, self::SHADOW_BROKER,
            self::ARCHIVIST, self::RUMOUR_MERCHANT                   => 'information_broker',
        };
    }

    /**
     * Generate a personality array with jitter applied to the canonical vector.
     * Tier 1+2 traits get ±10% jitter; Tier 3 traits get ±15% jitter.
     */
    public function generatePersonality(): array
    {
        $tier3 = [
            'honesty', 'charm', 'empathy', 'curiosity', 'loyalty',
            'grudge_factor', 'gossip_factor', 'paranoia', 'pride_in_work',
            'ego_drive', 'fear_threshold', 'formality', 'verbosity',
        ];

        $result = [];
        foreach ($this->traitVector() as $trait => $base) {
            $jitter = in_array($trait, $tier3) ? 0.15 : 0.10;
            $delta = (random_int(-100, 100) / 100) * $jitter;
            $result[$trait] = round(max(0.01, min(0.99, $base + $delta)), 2);
        }

        return $result;
    }
}
