
<script setup lang="ts">
import { onMounted, onBeforeUnmount, ref, watch } from 'vue'
import 'leaflet/dist/leaflet.css'

interface Coordinates {
  latitude: number | null
  longitude: number | null
}

const props = withDefaults(defineProps<{
  title: string
  modelValue: Coordinates
  allowCurrentLocation?: boolean
}>(), {
  allowCurrentLocation: false
})

const emit = defineEmits<{
  'update:modelValue': [value: Coordinates]
}>()

const mapElement = ref<HTMLElement | null>(null)
const locating = ref(false)
const locationError = ref('')

let map: any = null
let marker: any = null
let leaflet: any = null

const defaultLatitude = 0.5071
const defaultLongitude = 101.4478

const setLocation = (latitude: number, longitude: number) => {
  emit('update:modelValue', {
    latitude,
    longitude
  })

  if (map && leaflet) {
    const position = [latitude, longitude]

    if (!marker) {
      marker = leaflet.circleMarker(position, {
        radius: 9,
        color: '#ffd17c',
        weight: 3,
        fillColor: '#f2b34b',
        fillOpacity: 1
      }).addTo(map)
    } else {
      marker.setLatLng(position)
    }

    map.setView(position, 16)
  }
}

const useCurrentLocation = () => {
  if (!navigator.geolocation) {
    locationError.value = 'Browser tidak mendukung GPS.'
    return
  }

  locating.value = true
  locationError.value = ''

  navigator.geolocation.getCurrentPosition(
    (position) => {
      setLocation(
        position.coords.latitude,
        position.coords.longitude
      )

      locating.value = false
    },
    (error) => {
      const messages: Record<number, string> = {
        1: 'Izin lokasi ditolak. Izinkan akses lokasi browser.',
        2: 'Lokasi tidak dapat ditemukan.',
        3: 'GPS timeout. Silakan coba lagi.'
      }

      locationError.value =
        messages[error.code] || 'Gagal mengambil lokasi.'

      locating.value = false
    },
    {
      enableHighAccuracy: true,
      timeout: 15000,
      maximumAge: 0
    }
  )
}

watch(
  () => props.modelValue,
  (value) => {
    if (
      value.latitude !== null &&
      value.longitude !== null &&
      map &&
      leaflet
    ) {
      const position = [
        value.latitude,
        value.longitude
      ]

      if (!marker) {
        marker = leaflet.circleMarker(position, {
          radius: 9,
          color: '#ffd17c',
          weight: 3,
          fillColor: '#f2b34b',
          fillOpacity: 1
        }).addTo(map)
      } else {
        marker.setLatLng(position)
      }

      map.setView(position, 16)
    }
  },
  { deep: true }
)

onMounted(async () => {
  if (!mapElement.value) return

  leaflet = await import('leaflet')

  const hasCoordinates =
    props.modelValue.latitude !== null &&
    props.modelValue.longitude !== null

  const latitude = hasCoordinates
    ? props.modelValue.latitude!
    : defaultLatitude

  const longitude = hasCoordinates
    ? props.modelValue.longitude!
    : defaultLongitude

  map = leaflet.map(mapElement.value).setView(
    [latitude, longitude],
    hasCoordinates ? 16 : 13
  )

  leaflet.tileLayer(
    'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
    {
      attribution: '&copy; OpenStreetMap contributors',
      maxZoom: 19
    }
  ).addTo(map)

  map.on('click', (event: any) => {
    setLocation(
      event.latlng.lat,
      event.latlng.lng
    )
  })

  if (hasCoordinates) {
    marker = leaflet.circleMarker(
      [latitude, longitude],
      {
        radius: 9,
        color: '#ffd17c',
        weight: 3,
        fillColor: '#f2b34b',
        fillOpacity: 1
      }
    ).addTo(map)
  }

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
  <div class="location-picker">
    <div class="location-picker-heading">
      <strong>{{ title }}</strong>

      <button
        v-if="allowCurrentLocation"
        type="button"
        class="location-gps-button"
        :disabled="locating"
        @click="useCurrentLocation"
      >
        {{ locating ? 'Locating...' : '◎ Use My Location' }}
      </button>
    </div>

    <p class="location-picker-hint">
      Tap the map to select your location.
    </p>

    <div
      ref="mapElement"
      class="location-map"
    />

    <p v-if="locationError" class="location-error">
      {{ locationError }}
    </p>

    <div class="location-coordinates">
      <span>Latitude</span>
      <strong>
        {{ modelValue.latitude?.toFixed(7) ?? 'Not selected' }}
      </strong>

      <span>Longitude</span>
      <strong>
        {{ modelValue.longitude?.toFixed(7) ?? 'Not selected' }}
      </strong>
    </div>
  </div>
</template>
