
<script setup lang="ts">
import { onMounted, onBeforeUnmount, ref, watch } from 'vue'
import 'leaflet/dist/leaflet.css'

interface FleetCourier {
  order_id: number
  order_number: string
  courier_id: number
  courier_name: string
  latitude: number | string
  longitude: number | string
  accuracy: number | string | null
  heading: number | string | null
  speed: number | string | null
  recorded_at: string
}

const props = defineProps<{
  couriers: FleetCourier[]
}>()

const mapElement = ref<HTMLElement | null>(null)

let map: any = null
let leaflet: any = null

const markers = new Map<number, any>()
let hasFittedBounds = false


const updateMarkers = () => {
  if (!map || !leaflet) return

  const activeIds = new Set(
    props.couriers.map(courier => courier.order_id)
  )

  // Remove markers for orders no longer active
  for (const [orderId, marker] of markers) {
    if (!activeIds.has(orderId)) {
      marker.remove()
      markers.delete(orderId)
    }
  }

  const bounds: any[] = []

  for (const courier of props.couriers) {
    const latitude = Number(courier.latitude)
    const longitude = Number(courier.longitude)

    if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
      continue
    }

    const position: [number, number] = [latitude, longitude]
    bounds.push(position)

    let marker = markers.get(courier.order_id)

    if (!marker) {
      marker = leaflet.circleMarker(position, {
        radius: 10,
        color: '#ffffff',
        weight: 3,
        fillColor: '#f2b34b',
        fillOpacity: 1
      }).addTo(map)

      markers.set(courier.order_id, marker)
    } else {
      marker.setLatLng(position)
    }

    const popup = document.createElement('div')
    const name = document.createElement('strong')
    const order = document.createElement('div')

    name.textContent = courier.courier_name
    order.textContent = courier.order_number

    popup.append(name, order)
    marker.bindPopup(popup)
  }

  if (!hasFittedBounds && bounds.length === 1) {
  map.setView(bounds[0], 16)
  hasFittedBounds = true
} else if (!hasFittedBounds && bounds.length > 1) {
  map.fitBounds(
    leaflet.latLngBounds(bounds),
    { padding: [40, 40], maxZoom: 16 }
  )
  hasFittedBounds = true
}
}

watch(
  () => props.couriers,
  updateMarkers,
  { deep: true }
)

onMounted(async () => {
  if (!mapElement.value) return

  leaflet = await import('leaflet')

  map = leaflet.map(mapElement.value).setView(
    [0.5071, 101.4478],
    13
  )

  leaflet.tileLayer(
    'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
    {
      attribution: '&copy; OpenStreetMap contributors',
      maxZoom: 19
    }
  ).addTo(map)

  updateMarkers()

  setTimeout(() => {
    map?.invalidateSize()
  }, 200)
})

onBeforeUnmount(() => {
  markers.clear()

  if (map) {
    map.remove()
    map = null
  }
})
</script>

<template>
  <div ref="mapElement" class="fleet-monitoring-map" />
</template>

<style scoped>
.fleet-monitoring-map {
  width: 100%;
  height: 420px;
  border-radius: 14px;
  z-index: 1;
}
</style>
