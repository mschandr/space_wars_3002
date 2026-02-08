/**
 * TypeScript types for the Galaxy Creation API
 *
 * Endpoint: POST /api/galaxies/create
 */

// ============================================================================
// REQUEST TYPES
// ============================================================================

export type GalaxySizeTier = 'small' | 'medium' | 'large' | 'massive';

export type GameMode = 'multiplayer' | 'single_player';  // NPCs are generated in all game modes

export interface CreateOptimizedGalaxyRequest {
  /** Galaxy size tier (required) */
  size_tier: GalaxySizeTier;

  /** Game mode (required) */
  game_mode: GameMode;

  /** Custom galaxy name (optional, auto-generated if omitted) */
  name?: string;

  /** Skip mirror universe gate generation */
  skip_mirror?: boolean;

  /** Skip precursor content (gate + ship) */
  skip_precursors?: boolean;

  // Note: NPC configuration (npc_count, npc_difficulty) is NOT accepted via API.
  // NPCs are ALWAYS generated in all galaxies. Counts are determined automatically:
  // - small: 5 NPCs (easy)
  // - medium: 10 NPCs (medium)
  // - large: 15 NPCs (hard)
  // - massive: 25 NPCs (expert)
}

// ============================================================================
// RESPONSE TYPES
// ============================================================================

export interface GalaxyBasicInfo {
  id: number;
  uuid: string;
  name: string;
  status: 'active' | 'draft' | 'archived';
}

export interface GalaxyStatistics {
  total_pois: number;
  total_stars: number;
  core_stars: number;
  outer_stars: number;
  inhabited_systems: number;
  fortified_systems: number;
  warp_gates: number;
  active_gates: number;
  dormant_gates: number;
  trading_hubs: number;
}

export interface GeneratorCounts {
  [key: string]: number;
}

export interface GeneratorMetricsDetail {
  elapsed_ms: number;
  elapsed_seconds: number;
  counts: GeneratorCounts;
  custom?: Record<string, any>;
}

export interface GeneratorResult {
  success: boolean;
  metrics: GeneratorMetricsDetail;
  data?: Record<string, any>;
  error?: string | null;
}

export interface GenerationMetrics {
  total_elapsed_ms: number;
  total_elapsed_seconds: number;
  generators: {
    star_field: GeneratorResult;
    planetary_systems: GeneratorResult;
    warp_gate_network: GeneratorResult;
    mineral_deposits: GeneratorResult;
    defense_network: GeneratorResult;
    trading_infrastructure: GeneratorResult;
    precursor_content: GeneratorResult;
  };
}

export interface GenerationConfig {
  tier: GalaxySizeTier;
  game_mode: GameMode;
  dimensions: {
    width: number;
    height: number;
  };
  star_counts: {
    core: number;
    outer: number;
    total: number;
  };
}

export interface CreateOptimizedGalaxyResponseData {
  galaxy: GalaxyBasicInfo;
  statistics: GalaxyStatistics;
  metrics: GenerationMetrics;
  config: GenerationConfig;
}

export interface CreateOptimizedGalaxySuccessResponse {
  success: true;
  message: string;
  data: CreateOptimizedGalaxyResponseData;
}

export interface CreateOptimizedGalaxyErrorResponse {
  success: false;
  message: string;
  error_code: string;
  data?: {
    valid_tiers?: SizeTierOption[];
    metrics?: Partial<GenerationMetrics>;
  } | null;
}

export type CreateOptimizedGalaxyResponse =
  | CreateOptimizedGalaxySuccessResponse
  | CreateOptimizedGalaxyErrorResponse;

// ============================================================================
// SIZE TIER OPTIONS (from GET /api/galaxies/size-tiers)
// ============================================================================

export interface SizeTierOption {
  value: GalaxySizeTier;
  label: string;
  outer_bounds: number;
  core_bounds: number;
  core_stars: number;
  outer_stars: number;
  total_stars: number;
  secret?: boolean;
}

/** Public tiers returned by API */
export const PUBLIC_SIZE_TIERS: SizeTierOption[] = [
  {
    value: 'small',
    label: 'Small Galaxy (500×500)',
    outer_bounds: 500,
    core_bounds: 250,
    core_stars: 100,
    outer_stars: 150,
    total_stars: 250,
  },
  {
    value: 'medium',
    label: 'Medium Galaxy (1500×1500)',
    outer_bounds: 1500,
    core_bounds: 750,
    core_stars: 300,
    outer_stars: 450,
    total_stars: 750,
  },
  {
    value: 'large',
    label: 'Large Galaxy (2500×2500)',
    outer_bounds: 2500,
    core_bounds: 1250,
    core_stars: 500,
    outer_stars: 750,
    total_stars: 1250,
  },
];

/** Secret tier (not returned by API, use directly) */
export const MASSIVE_SIZE_TIER: SizeTierOption = {
  value: 'massive',
  label: 'Massive Galaxy (5000×5000)',
  outer_bounds: 5000,
  core_bounds: 2500,
  core_stars: 1000,
  outer_stars: 1500,
  total_stars: 2500,
  secret: true,
};

/** All tiers including secret */
export const ALL_SIZE_TIERS: SizeTierOption[] = [
  ...PUBLIC_SIZE_TIERS,
  MASSIVE_SIZE_TIER,
];

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Estimated generation time in seconds for each tier
 */
export const ESTIMATED_GENERATION_TIMES: Record<GalaxySizeTier, number> = {
  small: 8,
  medium: 20,
  large: 35,
  massive: 45,
};

/**
 * Get human-readable time estimate
 */
export function getTimeEstimate(tier: GalaxySizeTier): string {
  const seconds = ESTIMATED_GENERATION_TIMES[tier];
  if (seconds < 60) {
    return `~${seconds} seconds`;
  }
  const minutes = Math.ceil(seconds / 60);
  return `~${minutes} minute${minutes > 1 ? 's' : ''}`;
}

/**
 * Check if a tier is the secret massive tier
 */
export function isSecretTier(tier: GalaxySizeTier): boolean {
  return tier === 'massive';
}

/**
 * Get tier configuration by value
 */
export function getTierConfig(tier: GalaxySizeTier): SizeTierOption | undefined {
  return ALL_SIZE_TIERS.find(t => t.value === tier);
}

// ============================================================================
// GALAXY MEMBERSHIP TYPES
// ============================================================================

/**
 * Player information returned by membership endpoints
 */
export interface PlayerInfo {
  uuid: string;
  call_sign: string;
  credits: number;
  experience: number;
  level: number;
  status: 'active' | 'destroyed' | 'inactive';
  galaxy: {
    uuid: string;
    name: string;
  };
  location: {
    uuid: string;
    name: string;
    x: number;
    y: number;
  } | null;
  ship: {
    uuid: string;
    name: string;
    class: string;
  } | null;
}

/**
 * Request body for POST /api/galaxies/{uuid}/join
 */
export interface JoinGalaxyRequest {
  /** Call sign for new player (required only when creating) */
  call_sign: string;
}

/**
 * Response from GET /api/galaxies/{uuid}/my-player (success)
 */
export interface GetMyPlayerSuccessResponse {
  success: true;
  message: string;
  data: PlayerInfo;
}

/**
 * Response from GET /api/galaxies/{uuid}/my-player (not found)
 */
export interface GetMyPlayerNotFoundResponse {
  success: false;
  message: string;
  error: {
    code: 'NO_PLAYER_IN_GALAXY';
    details: {
      galaxy_uuid: string;
    };
  };
}

export type GetMyPlayerResponse =
  | GetMyPlayerSuccessResponse
  | GetMyPlayerNotFoundResponse;

/**
 * Response from POST /api/galaxies/{uuid}/join (success)
 */
export interface JoinGalaxySuccessResponse {
  success: true;
  message: string;
  data: {
    player: PlayerInfo;
    /** true if player was created, false if already existed */
    created: boolean;
  };
}

/**
 * Error codes for join galaxy endpoint
 */
export type JoinGalaxyErrorCode =
  | 'GALAXY_NOT_ACTIVE'
  | 'GALAXY_FULL'
  | 'SINGLE_PLAYER_GALAXY'
  | 'DUPLICATE_CALL_SIGN'
  | 'NO_STARTING_LOCATION'
  | 'JOIN_FAILED';

/**
 * Response from POST /api/galaxies/{uuid}/join (error)
 */
export interface JoinGalaxyErrorResponse {
  success: false;
  message: string;
  error: {
    code: JoinGalaxyErrorCode;
    details?: {
      max_players?: number;
      current_players?: number;
      status?: string;
    };
  };
}

export type JoinGalaxyResponse =
  | JoinGalaxySuccessResponse
  | JoinGalaxyErrorResponse;

// ============================================================================
// GALAXY MEMBERSHIP HELPER FUNCTIONS
// ============================================================================

/**
 * Check if user has a player in a galaxy
 * @returns Player info if exists, null if not
 */
export async function checkPlayerInGalaxy(
  galaxyUuid: string,
  token: string
): Promise<PlayerInfo | null> {
  const response = await fetch(`/api/galaxies/${galaxyUuid}/my-player`, {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Accept': 'application/json',
    },
  });

  if (response.status === 404) {
    return null;
  }

  const result: GetMyPlayerResponse = await response.json();

  if (result.success) {
    return result.data;
  }

  return null;
}

/**
 * Join a galaxy - idempotent operation
 * Returns existing player or creates new one
 */
export async function joinGalaxy(
  galaxyUuid: string,
  callSign: string,
  token: string
): Promise<{ player: PlayerInfo; created: boolean }> {
  const response = await fetch(`/api/galaxies/${galaxyUuid}/join`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`,
      'Accept': 'application/json',
    },
    body: JSON.stringify({ call_sign: callSign }),
  });

  const result: JoinGalaxyResponse = await response.json();

  if (!result.success) {
    const error = new Error(result.message);
    (error as any).code = result.error.code;
    (error as any).details = result.error.details;
    throw error;
  }

  return result.data;
}

// ============================================================================
// EXAMPLE USAGE
// ============================================================================

/*
import {
  CreateOptimizedGalaxyRequest,
  CreateOptimizedGalaxyResponse,
  GalaxySizeTier,
  getTimeEstimate
} from './types/galaxy-creation';

async function createGalaxy(
  tier: GalaxySizeTier,
  gameMode: GameMode
): Promise<CreateOptimizedGalaxyResponse> {
  const payload: CreateOptimizedGalaxyRequest = {
    size_tier: tier,
    game_mode: gameMode,
  };

  const response = await fetch('/api/galaxies/create', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`,
    },
    body: JSON.stringify(payload),
    signal: AbortSignal.timeout(120000), // 2 min timeout for large galaxies
  });

  return response.json();
}

// Usage - Create Galaxy
const result = await createGalaxy('massive', 'multiplayer');

if (result.success) {
  console.log(`Created galaxy: ${result.data.galaxy.name}`);
  console.log(`Total POIs: ${result.data.statistics.total_pois}`);
  console.log(`Generation time: ${result.data.metrics.total_elapsed_seconds}s`);
} else {
  console.error(`Error: ${result.message}`);
}

// Usage - Check Player Membership
const existingPlayer = await checkPlayerInGalaxy(galaxyUuid, token);
if (existingPlayer) {
  console.log(`You have a player: ${existingPlayer.call_sign}`);
} else {
  console.log('You need to join this galaxy first');
}

// Usage - Join Galaxy (idempotent)
try {
  const { player, created } = await joinGalaxy(galaxyUuid, 'MyCallSign', token);

  if (created) {
    console.log(`Welcome aboard, ${player.call_sign}!`);
  } else {
    console.log(`Welcome back, ${player.call_sign}!`);
  }

  // Navigate to game
  router.push(`/game/${galaxyUuid}`);

} catch (error) {
  switch (error.code) {
    case 'GALAXY_FULL':
      alert('This galaxy is full!');
      break;
    case 'DUPLICATE_CALL_SIGN':
      alert('That call sign is taken. Try another.');
      break;
    case 'SINGLE_PLAYER_GALAXY':
      alert('This is a private galaxy.');
      break;
    default:
      alert('Failed to join galaxy');
  }
}
*/
