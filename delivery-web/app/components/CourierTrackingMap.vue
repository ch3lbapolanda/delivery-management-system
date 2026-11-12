
<script setup lang="ts">
import { onMounted, onBeforeUnmount, ref, watch } from 'vue'
import 'leaflet/dist/leaflet.css'

const props = defineProps<{
  latitude: number
  longitude: number
}>()

const mapElement = ref<HTMLElement | null>(null)

let map: any = null
let marker: any = null
let leaflet: any = null

const updateMarker = () => {
  if (!map || !leaflet) return

  const position = [props.latitude, props.longitude]

  if (!marker) {
    marker = leaflet.circleMarker(position, {
      radius: 10,
      color: '#ffffff',
      weight: 3,
      fillColor: '#f2b34b',
      fillOpacity: 1
    }).addTo(map)

    marker.bindPopup('Courier location')
  } else {
    marker.setLatLng(position)
  }

  map.panTo(position)
}

watch(
  () => [props.latitude, props.longitude],
  updateMarker
)

onMounted(async () => {
  if (!mapElement.value) return

  leaflet = await import('leaflet')

  map = leaflet.map(mapElement.value).setView(
    [props.latitude, props.longitude],
    16
  )

  leaflet.tileLayer(
    'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
    {
      attribution: '&copy; OpenStreetMap contributors',
      maxZoom: 19
    }
  ).addTo(map)

  updateMarker()

  setTimeout(() => {
    map?.invalidateSize()
  }, 200)
})

onBeforeUnmount(() => {
  if (map) {
    map.remove()
    map = null
  }
})
</script>

<template>
  <div ref="mapElement" class="courier-tracking-map" />
</template>
