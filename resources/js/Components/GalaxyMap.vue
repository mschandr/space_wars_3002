<template>
    <canvas
        ref="canvas"
        :width="width"
        :height="height"
        class="border"
    ></canvas>
</template>

<script setup>
import { onMounted, ref } from 'vue'

const props = defineProps({
    dataUrl: { type: String, required: true },
    width: { type: Number, default: 600 },
    height: { type: Number, default: 600 },
})

const canvas = ref(null)

onMounted(async () => {
    const res = await fetch(props.dataUrl)
    const data = await res.json()

    if (!data.points || data.points.length === 0) {
        console.warn("No points found in data:", data)
        return
    }

    const ctx = canvas.value.getContext('2d')

    // background
    ctx.fillStyle = 'black'
    ctx.fillRect(0, 0, props.width, props.height)

    // figure out bounds of galaxy data
    const maxX = Math.max(...data.points.map(([x]) => x))
    const maxY = Math.max(...data.points.map(([_, y]) => y))

    // scaling factors
    const scaleX = props.width / maxX
    const scaleY = props.height / maxY

    // draw points
    ctx.fillStyle = 'white'
    data.points.forEach(([x, y]) => {
        ctx.beginPath()
        ctx.arc(x * scaleX, y * scaleY, 2, 0, Math.PI * 2)
        ctx.fill()
    })
})
</script>

<style scoped>
canvas {
    display: block;
    margin: 0 auto;
}
</style>
