<?php

return [

    /*
     * Internal API token used to authenticate the Go dialogue generator.
     * Go must send: Authorization: Bearer <token>
     * Set DIALOGUE_GENERATOR_TOKEN in .env
     */
    'internal_token' => env('DIALOGUE_GENERATOR_TOKEN', null),

    /*
     * Optional: URL of the Go generator service.
     * Used by TriggerVendorDialogueRegenerationJob (Phase 6) to wake up the generator.
     * Set DIALOGUE_GENERATOR_URL in .env
     */
    'generator_url' => env('DIALOGUE_GENERATOR_URL', null),

    /*
     * Maximum number of dialogue lines accepted per vendor per submission request.
     * Go submits lines per scope (line_type + bucket + context combination).
     */
    'max_lines_per_submission' => 20,

    /*
     * Validation constraints mirroring Go's validation rules.
     * Both sides must reject lines outside these bounds.
     */
    'validation' => [
        'min_words'      => 6,
        'max_words'      => 20,
        'max_characters' => 255,
    ],

    /*
     * Generation matrix: the canonical (line_type, interaction_bucket,
     * transaction_context, inventory_context) combinations the Go generator
     * is expected to produce per vendor. PHP uses this for coverage reporting
     * in the vendor:dialogue-status command.
     *
     * Source: docs/design/vendor_dialogue_joint_design_go_php.md § 9.2
     */
    'generation_matrix' => [
        // Greetings (4 buckets × neutral)
        ['line_type' => 'greeting', 'bucket' => 'first_visit',     'transaction_context' => 'neutral', 'inventory_context' => 'none'],
        ['line_type' => 'greeting', 'bucket' => 'second_visit',    'transaction_context' => 'neutral', 'inventory_context' => 'none'],
        ['line_type' => 'greeting', 'bucket' => 'third_visit',     'transaction_context' => 'neutral', 'inventory_context' => 'none'],
        ['line_type' => 'greeting', 'bucket' => 'repeat_customer', 'transaction_context' => 'neutral', 'inventory_context' => 'none'],

        // Inventory pitches (vendor_selling × all item categories)
        ['line_type' => 'inventory_pitch', 'bucket' => 'repeat_customer', 'transaction_context' => 'vendor_selling', 'inventory_context' => 'ship'],
        ['line_type' => 'inventory_pitch', 'bucket' => 'repeat_customer', 'transaction_context' => 'vendor_selling', 'inventory_context' => 'shield_projector'],
        ['line_type' => 'inventory_pitch', 'bucket' => 'repeat_customer', 'transaction_context' => 'vendor_selling', 'inventory_context' => 'engine'],
        ['line_type' => 'inventory_pitch', 'bucket' => 'repeat_customer', 'transaction_context' => 'vendor_selling', 'inventory_context' => 'reactor'],
        ['line_type' => 'inventory_pitch', 'bucket' => 'repeat_customer', 'transaction_context' => 'vendor_selling', 'inventory_context' => 'weapon'],
        ['line_type' => 'inventory_pitch', 'bucket' => 'repeat_customer', 'transaction_context' => 'vendor_selling', 'inventory_context' => 'sensor_array'],
        ['line_type' => 'inventory_pitch', 'bucket' => 'repeat_customer', 'transaction_context' => 'vendor_selling', 'inventory_context' => 'cargo_module'],
        ['line_type' => 'inventory_pitch', 'bucket' => 'repeat_customer', 'transaction_context' => 'vendor_selling', 'inventory_context' => 'hull_plating'],
        ['line_type' => 'inventory_pitch', 'bucket' => 'repeat_customer', 'transaction_context' => 'vendor_selling', 'inventory_context' => 'salvage_component'],

        // Deal responses (vendor_selling + vendor_buying)
        ['line_type' => 'deal_accepted', 'bucket' => 'repeat_customer', 'transaction_context' => 'vendor_selling', 'inventory_context' => 'none'],
        ['line_type' => 'deal_accepted', 'bucket' => 'repeat_customer', 'transaction_context' => 'vendor_buying',  'inventory_context' => 'none'],
        ['line_type' => 'deal_rejected', 'bucket' => 'repeat_customer', 'transaction_context' => 'vendor_selling', 'inventory_context' => 'none'],
        ['line_type' => 'deal_rejected', 'bucket' => 'repeat_customer', 'transaction_context' => 'vendor_buying',  'inventory_context' => 'none'],

        // Farewell
        ['line_type' => 'farewell', 'bucket' => 'repeat_customer', 'transaction_context' => 'neutral', 'inventory_context' => 'none'],
    ],

];
