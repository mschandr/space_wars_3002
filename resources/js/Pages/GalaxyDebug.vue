<template>
    <div class="p-4">
        <h1 class="text-xl text-white mb-4">Galaxy Debug Map</h1>
        <GalaxyMap data-url="/storage/galaxy.json" :width="600" :height="600" />
    </div>
</template>

<script setup>
import GalaxyMap from '@/Components/GalaxyMap.vue'
</script>
