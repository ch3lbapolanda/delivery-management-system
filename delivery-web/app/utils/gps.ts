export interface GpsTimestamped {
  recorded_at: string
}

export const GPS_FUTURE_TOLERANCE_MS = 120_000

export const isValidGpsTimestamp = (
  recordedAt: string,
  now = Date.now()
): boolean => {
  const timestamp = Date.parse(recordedAt)

  return (
    Number.isFinite(timestamp) &&
    timestamp <= now + GPS_FUTURE_TOLERANCE_MS
  )
}

export const shouldUpdateGpsLocation = (
  incoming: GpsTimestamped | null,
  current: GpsTimestamped | null,
  now = Date.now()
): boolean => {
  // Jangan pernah mengganti lokasi dengan null.
  if (!incoming) return false

  if (!isValidGpsTimestamp(incoming.recorded_at, now)) {
    return false
  }

  // Belum ada lokasi sebelumnya.
  if (!current) return true

  const incomingTimestamp = Date.parse(incoming.recorded_at)
  const currentTimestamp = Date.parse(current.recorded_at)

  // Lokasi sebelumnya invalid, terima lokasi baru yang valid.
  if (!isValidGpsTimestamp(current.recorded_at, now)) {
    return true
  }

  // Hanya timestamp yang lebih baru yang diterima.
  return incomingTimestamp > currentTimestamp
}