<template>
    <div class="galaxy-map">
        <canvas
            ref="canvas"
            :width="width"
            :height="height"
            @click="handleClick"
        ></canvas>
    </div>
</template>

<script setup>
import { onMounted, ref } from "vue";

const props = defineProps({
    dataUrl: { type: String, required: true },
    width: { type: Number, default: 600 },
    height: { type: Number, default: 600 },
});

const canvas = ref(null);
let ctx = null;
let points = [];
const sectorsX = 10;
const sectorsY = 10;
const sectorWidth = props.width / sectorsX;
const sectorHeight = props.height / sectorsY;
let zoomedSector = null;

const drawGalaxy = () => {
    ctx.clearRect(0, 0, props.width, props.height);
    ctx.fillStyle = "black";
    ctx.fillRect(0, 0, props.width, props.height);

    // Visible bounds
    let xMin = 0, yMin = 0, xMax = 300, yMax = 300;
    if (zoomedSector !== null) {
        const sx = zoomedSector % sectorsX;
        const sy = Math.floor(zoomedSector / sectorsX);
        xMin = (sx * 300) / sectorsX;
        yMin = (sy * 300) / sectorsY;
        xMax = ((sx + 1) * 300) / sectorsX;
        yMax = ((sy + 1) * 300) / sectorsY;
    }

    // Scale dot size (denser in zoom view)
    const pointSize = zoomedSector === null ? 2 : 4;

    // Draw points
    ctx.fillStyle = "white";
    points.forEach(([x, y]) => {
        if (x >= xMin && x < xMax && y >= yMin && y < yMax) {
            const px = ((x - xMin) / (xMax - xMin)) * props.width;
            const py = ((y - yMin) / (yMax - yMin)) * props.height;
            ctx.fillRect(px, py, pointSize, pointSize);
        }
    });

    // Grid + Labels
    if (zoomedSector === null) {
        ctx.strokeStyle = "rgba(255, 0, 0, 0.8)";
        ctx.lineWidth = 2;
        for (let i = 1; i < sectorsX; i++) {
            const x = i * sectorWidth;
            ctx.beginPath();
            ctx.moveTo(x, 0);
            ctx.lineTo(x, props.height);
            ctx.stroke();
        }
        for (let j = 1; j < sectorsY; j++) {
            const y = j * sectorHeight;
            ctx.beginPath();
            ctx.moveTo(0, y);
            ctx.lineTo(props.width, y);
            ctx.stroke();
        }

        // Labels
        ctx.fillStyle = "yellow";
        ctx.font = "10px monospace";
        ctx.textAlign = "center";
        ctx.textBaseline = "middle";

        let sectorId = 0;
        for (let j = 0; j < sectorsY; j++) {
            for (let i = 0; i < sectorsX; i++) {
                const label = (sectorId + 1).toString().padStart(3, "0");
                const x = i * sectorWidth + sectorWidth / 2;
                const y = j * sectorHeight + sectorHeight / 2;
                ctx.fillText(label, x, y);
                sectorId++;
            }
        }
    } else {
        // Show zoomed sector label
        const label = (zoomedSector + 1).toString().padStart(3, "0");
        ctx.fillStyle = "yellow";
        ctx.font = "24px monospace";
        ctx.textAlign = "center";
        ctx.textBaseline = "middle";
        ctx.fillText("Sector " + label + " (click to return)", props.width / 2, props.height / 2);
    }
};

const handleClick = (event) => {
    const rect = canvas.value.getBoundingClientRect();
    const x = event.clientX - rect.left;
    const y = event.clientY - rect.top;

    if (zoomedSector === null) {
        const col = Math.floor(x / sectorWidth);
        const row = Math.floor(y / sectorHeight);
        zoomedSector = row * sectorsX + col;
    } else {
        zoomedSector = null;
    }

    drawGalaxy();
};

onMounted(async () => {
    ctx = canvas.value.getContext("2d");
    const res = await fetch(props.dataUrl);
    const data = await res.json();
    points = data.points || data;
    drawGalaxy();
});
</script>

<style scoped>
.galaxy-map {
    display: flex;
    flex-direction: column;
    align-items: center;
}
canvas {
    border: 2px solid #555;
    cursor: pointer;
}
</style>
