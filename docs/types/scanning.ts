/**
 * Sensor-Based Progressive Scan System
 *
 * This file defines TypeScript types for the scanning API.
 * The scanning system reveals information about star systems based on ship sensor level.
 *
 * Key Concepts:
 * - Sensor level determines scan depth (1-9)
 * - Higher levels reveal more detailed information
 * - Scan data is cached per player per system
 * - Systems have baseline scan levels based on region
 *
 * @see docs/API_SCANNING.md for full documentation
 */

// =============================================================================
// Enums & Constants
// =============================================================================

/**
 * Scan levels correspond to ship sensor levels (1-9)
 */
export enum ScanLevel {
  UNSCANNED = 0,
  GEOGRAPHY = 1,
  GATES = 2,
  BASIC_RESOURCES = 3,
  RARE_RESOURCES = 4,
  HIDDEN_FEATURES = 5,
  ANOMALIES = 6,
  DEEP_SCAN = 7,
  ADVANCED_INTEL = 8,
  PRECURSOR_SECRETS = 9,
}

/**
 * What each scan level reveals
 */
export const SCAN_LEVEL_REVEALS: Record<ScanLevel, string[]> = {
  [ScanLevel.UNSCANNED]: [],
  [ScanLevel.GEOGRAPHY]: ['geography', 'planet_count', 'planet_types', 'habitability_basic'],
  [ScanLevel.GATES]: ['gates_presence', 'gate_status'],
  [ScanLevel.BASIC_RESOURCES]: ['minerals_basic', 'gas_giant_resources'],
  [ScanLevel.RARE_RESOURCES]: ['minerals_rare', 'asteroid_resources'],
  [ScanLevel.HIDDEN_FEATURES]: ['hidden_moons', 'orbital_mining', 'ring_deposits'],
  [ScanLevel.ANOMALIES]: ['anomalies', 'ruins', 'derelicts'],
  [ScanLevel.DEEP_SCAN]: ['deep_scan', 'subsurface', 'terraforming'],
  [ScanLevel.ADVANCED_INTEL]: ['intel', 'pirate_hideouts', 'hidden_bases'],
  [ScanLevel.PRECURSOR_SECRETS]: ['precursor_gates', 'precursor_tech', 'ancient_secrets'],
};

/**
 * UI color scheme for scan levels (hex colors)
 */
export const SCAN_LEVEL_COLORS: Record<ScanLevel, string> = {
  [ScanLevel.UNSCANNED]: '#1a1a2e',
  [ScanLevel.GEOGRAPHY]: '#4a4a6a',
  [ScanLevel.GATES]: '#4a4a6a',
  [ScanLevel.BASIC_RESOURCES]: '#3366aa',
  [ScanLevel.RARE_RESOURCES]: '#3366aa',
  [ScanLevel.HIDDEN_FEATURES]: '#33aa66',
  [ScanLevel.ANOMALIES]: '#33aa66',
  [ScanLevel.DEEP_SCAN]: '#aa9933',
  [ScanLevel.ADVANCED_INTEL]: '#aa9933',
  [ScanLevel.PRECURSOR_SECRETS]: '#ff6600',
};

/**
 * UI opacity for scan levels (0.0 - 1.0)
 */
export const SCAN_LEVEL_OPACITIES: Record<ScanLevel, number> = {
  [ScanLevel.UNSCANNED]: 0.2,
  [ScanLevel.GEOGRAPHY]: 0.4,
  [ScanLevel.GATES]: 0.4,
  [ScanLevel.BASIC_RESOURCES]: 0.6,
  [ScanLevel.RARE_RESOURCES]: 0.6,
  [ScanLevel.HIDDEN_FEATURES]: 0.8,
  [ScanLevel.ANOMALIES]: 0.8,
  [ScanLevel.DEEP_SCAN]: 0.9,
  [ScanLevel.ADVANCED_INTEL]: 0.9,
  [ScanLevel.PRECURSOR_SECRETS]: 1.0,
};

/**
 * Human-readable labels for scan levels
 */
export const SCAN_LEVEL_LABELS: Record<ScanLevel, string> = {
  [ScanLevel.UNSCANNED]: 'Unscanned',
  [ScanLevel.GEOGRAPHY]: 'Basic Geography',
  [ScanLevel.GATES]: 'Gate Detection',
  [ScanLevel.BASIC_RESOURCES]: 'Basic Resources',
  [ScanLevel.RARE_RESOURCES]: 'Rare Resources',
  [ScanLevel.HIDDEN_FEATURES]: 'Hidden Features',
  [ScanLevel.ANOMALIES]: 'Anomaly Detection',
  [ScanLevel.DEEP_SCAN]: 'Deep Scan',
  [ScanLevel.ADVANCED_INTEL]: 'Advanced Intel',
  [ScanLevel.PRECURSOR_SECRETS]: 'Precursor Secrets',
};

// =============================================================================
// API Response Types
// =============================================================================

/**
 * Standard API response wrapper
 */
export interface ApiResponse<T> {
  success: boolean;
  data: T;
  message: string;
  meta: {
    timestamp: string;
    request_id: string;
  };
}

/**
 * Scan display information for UI rendering
 */
export interface ScanDisplay {
  /** Hex color for this scan level */
  color: string;
  /** Opacity (0.0 - 1.0) for fog-of-war effect */
  opacity: number;
  /** Human-readable label */
  label: string;
}

/**
 * Basic system information (always visible)
 */
export interface SystemInfo {
  uuid: string;
  name: string;
  type: string;
  coordinates: { x: number; y: number };
  is_inhabited: boolean;
}

// =============================================================================
// POST /api/players/{uuid}/scan-system
// =============================================================================

/**
 * Request body for scanning a system
 */
export interface ScanSystemRequest {
  /** Optional: UUID of system to scan (defaults to current location) */
  poi_uuid?: string;
  /** Force re-scan even if already scanned at this level */
  force?: boolean;
}

/**
 * Response from scanning a system
 */
export interface ScanSystemResponse {
  system: {
    uuid: string;
    name: string;
    coordinates: { x: number; y: number };
  };
  /** Achieved scan level (1-9) */
  scan_level: number;
  /** Combined scan data from all levels up to scan_level */
  scan_data: ScanData;
  /** True if using cached data (no new scan performed) */
  cached: boolean;
  /** True if higher sensor levels would reveal more */
  can_reveal_more: boolean;
  /** Categories that next level would reveal (null if at max) */
  next_level_reveals: string[] | null;
  /** Level keys of newly discovered data (empty if cached) */
  new_discoveries: string[];
}

// =============================================================================
// GET /api/players/{uuid}/scan-results/{poiUuid}
// =============================================================================

/**
 * Response from getting scan results for a system
 */
export interface GetScanResultsResponse {
  system: SystemInfo;
  scan: {
    /** Achieved scan level */
    scan_level: number;
    /** Combined scan data */
    scan_data: ScanData;
    /** When the scan was performed (null for baseline) */
    scanned_at: string | null;
    /** True if this is baseline intel (not player-scanned) */
    baseline?: boolean;
    /** True if higher levels available */
    can_reveal_more: boolean;
    /** What next level reveals */
    next_level_reveals: string[] | null;
    /** UI display properties */
    display: ScanDisplay;
  };
}

// =============================================================================
// GET /api/players/{uuid}/exploration-log
// =============================================================================

/**
 * A single entry in the exploration log
 */
export interface ExplorationLogEntry {
  uuid: string;
  system: {
    uuid: string;
    name: string;
    type: string;
    coordinates: { x: number; y: number };
    is_inhabited: boolean;
    region: 'core' | 'outer' | 'unknown';
  };
  scan_level: number;
  scan_level_label: string;
  scanned_at: string;
  can_reveal_more: boolean;
  display: {
    color: string;
    opacity: number;
  };
}

/**
 * Response from getting the exploration log
 */
export interface ExplorationLogResponse {
  entries: ExplorationLogEntry[];
  statistics: {
    total_scanned: number;
    by_level: Record<string, number>;
    by_region: Record<string, number>;
  };
}

// =============================================================================
// POST /api/players/{uuid}/bulk-scan-levels
// =============================================================================

/**
 * Request body for bulk scan level lookup
 */
export interface BulkScanLevelsRequest {
  /** Array of POI UUIDs (max 500) */
  poi_uuids: string[];
}

/**
 * Scan level info for a single system
 */
export interface BulkScanLevelEntry {
  scan_level: number;
  color: string;
  opacity: number;
  label: string;
}

/**
 * Response from bulk scan levels lookup
 */
export interface BulkScanLevelsResponse {
  /** Map of POI UUID to scan level info */
  scan_levels: Record<string, BulkScanLevelEntry>;
}

// =============================================================================
// GET /api/players/{uuid}/system-data/{poiUuid}
// =============================================================================

/**
 * Response from getting filtered system data
 */
export interface SystemDataResponse {
  system_data: FilteredSystemData;
  scan_level: number;
}

// =============================================================================
// Scan Data Types (Progressive Revelation)
// =============================================================================

/**
 * Combined scan data from all achieved levels
 */
export interface ScanData {
  /** Level 1: Geography */
  geography?: GeographyData;
  /** Level 2: Gates */
  gates?: GateData;
  /** Level 3: Basic resources */
  resources?: BasicResourceData;
  /** Level 4: Rare resources */
  rare_resources?: RareResourceData;
  /** Level 5: Hidden features */
  hidden_features?: HiddenFeatureData;
  /** Level 6: Anomalies */
  anomalies?: AnomalyData;
  /** Level 7: Deep scan */
  deep_scan?: DeepScanData;
  /** Level 8: Intel */
  intel?: IntelData;
  /** Level 9: Precursor secrets */
  precursor?: PrecursorData;
}

/**
 * Filtered system data based on scan level
 */
export interface FilteredSystemData {
  uuid: string;
  name: string;
  scan_level: number;
  coordinates: { x: number; y: number };
  geography?: GeographyData;
  gates?: GateData;
  resources?: BasicResourceData;
  rare_resources?: RareResourceData;
  hidden_features?: HiddenFeatureData;
  anomalies?: AnomalyData;
  deep_scan?: DeepScanData;
  intel?: IntelData;
  precursor?: PrecursorData;
}

// =============================================================================
// Level-Specific Data Types
// =============================================================================

/**
 * Level 1: Geography data
 */
export interface GeographyData {
  star_type: string;
  planet_count: number;
  planet_types: {
    rocky: number;
    gas: number;
    ice: number;
    other: number;
  };
  dwarf_planets: number;
  asteroid_belts: number;
  habitability: {
    goldilocks_planets: number;
    notes: string[];
  };
}

/**
 * Level 2: Gate data
 */
export interface GateData {
  gate_count: number;
  active_gates: number;
  dormant_gates: number;
  gates: Array<{
    status: 'active' | 'dormant' | 'destroyed';
    destination: 'known' | 'unknown';
    activation_hint?: string;
  }>;
}

/**
 * Level 3: Basic resource data
 */
export interface BasicResourceData {
  rocky_planets: string[];
  gas_giants: string[];
}

/**
 * Level 4: Rare resource data
 */
export interface RareResourceData {
  asteroid_minerals: string[];
  rare_deposits: Array<{
    location: string;
    minerals: string[];
  }>;
}

/**
 * Level 5: Hidden feature data
 */
export interface HiddenFeatureData {
  habitable_moons: Array<{
    name: string;
    parent: string;
    climate: string;
  }>;
  orbital_mining: Array<{
    location: string;
    richness: 'moderate' | 'rich' | 'exceptional';
  }>;
  ring_deposits: Array<{
    planet: string;
    deposits: string[];
  }>;
}

/**
 * Level 6: Anomaly data
 */
export interface AnomalyData {
  ruins: Array<{
    location: string;
    type: string;
    age_estimate: string;
  }>;
  spatial_anomalies: Array<{
    name: string;
    type: string;
    danger_level: string;
  }>;
  derelicts: Array<{
    name: string;
    ship_class: string;
    salvageable: boolean;
  }>;
}

/**
 * Level 7: Deep scan data
 */
export interface DeepScanData {
  subsurface_deposits: Array<{
    planet: string;
    minerals: string[];
    depth: string;
  }>;
  core_composition: Array<{
    planet: string;
    type: string;
    stability: string;
  }>;
  terraforming: Array<{
    planet: string;
    viable: boolean;
    difficulty: string;
    time_estimate: string;
  }>;
}

/**
 * Level 8: Intel data
 */
export interface IntelData {
  pirate_hideouts: Array<{
    gate_id: string;
    threat_level: string;
  }>;
  hidden_bases: Array<{
    type: string;
    faction: string;
  }>;
  cloaked_structures: Array<{
    name: string;
    type: string;
  }>;
}

/**
 * Level 9: Precursor data
 */
export interface PrecursorData {
  hidden_gates: Array<{
    type: string;
    status: string;
    requires: object | null;
  }>;
  tech_caches: Array<{
    location: string;
    contents: string[];
    danger_level: string;
  }>;
  ancient_secrets: Array<{
    location: string;
    type: string;
    hint: string;
  }>;
}

// =============================================================================
// Galaxy Map Integration
// =============================================================================

/**
 * System data in galaxy map response (includes scan info)
 */
export interface MapSystemData {
  uuid: string;
  name: string;
  type: string;
  x: number;
  y: number;
  is_inhabited: boolean;
  is_current_location: boolean;
  scan: {
    level: number;
    label: string;
    color: string;
    opacity: number;
  };
}

// =============================================================================
// Travel Integration
// =============================================================================

/**
 * Auto-scan data returned with travel responses
 */
export interface TravelScanResult {
  scan_level: number;
  cached: boolean;
  new_discoveries: string[];
  can_reveal_more: boolean;
}

/**
 * Extended travel response with scan data
 */
export interface TravelResponseWithScan {
  success: boolean;
  message: string;
  destination: string;
  distance: number;
  fuel_cost: number;
  fuel_remaining: number;
  xp_earned: number;
  old_level: number;
  new_level: number;
  leveled_up: boolean;
  /** Auto-scan result (if enabled) */
  scan?: TravelScanResult;
}
