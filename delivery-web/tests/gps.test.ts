import { describe, expect, it } from 'vitest'

import {
  isValidGpsTimestamp,
  shouldUpdateGpsLocation
} from '../app/utils/gps'

const NOW = new Date('2026-11-15T02:00:00.000Z').getTime()

const location = (recordedAt: string) => ({
  recorded_at: recordedAt
})

describe('GPS timestamp validation', () => {
  it('accepts a valid timestamp', () => {
    expect(
      isValidGpsTimestamp(
        '2026-11-15T02:00:00.000Z',
        NOW
      )
    ).toBe(true)
  })

  it('rejects an invalid timestamp', () => {
    expect(
      isValidGpsTimestamp('invalid-date', NOW)
    ).toBe(false)
  })

  it('accepts future timestamp within 2 minutes', () => {
    expect(
      isValidGpsTimestamp(
        '2026-11-15T02:02:00.000Z',
        NOW
      )
    ).toBe(true)
  })

  it('rejects future timestamp beyond 2 minutes', () => {
    expect(
      isValidGpsTimestamp(
        '2026-11-15T02:03:00.000Z',
        NOW
      )
    ).toBe(false)
  })
})

describe('GPS location consistency', () => {
  it('accepts the first valid location', () => {
    expect(
      shouldUpdateGpsLocation(
        location('2026-11-15T02:00:00.000Z'),
        null,
        NOW
      )
    ).toBe(true)
  })

  it('accepts a newer location', () => {
    expect(
      shouldUpdateGpsLocation(
        location('2026-11-15T02:00:20.000Z'),
        location('2026-11-15T02:00:10.000Z'),
        NOW
      )
    ).toBe(true)
  })

  it('rejects an older location', () => {
    expect(
      shouldUpdateGpsLocation(
        location('2026-11-15T01:59:00.000Z'),
        location('2026-11-15T02:00:00.000Z'),
        NOW
      )
    ).toBe(false)
  })

  it('rejects a location with the same timestamp', () => {
    const timestamp = '2026-11-15T02:00:00.000Z'

    expect(
      shouldUpdateGpsLocation(
        location(timestamp),
        location(timestamp),
        NOW
      )
    ).toBe(false)
  })

  it('rejects null API response', () => {
    expect(
      shouldUpdateGpsLocation(
        null,
        location('2026-11-15T02:00:00.000Z'),
        NOW
      )
    ).toBe(false)
  })

  it('accepts a valid location if current location is invalid', () => {
    expect(
      shouldUpdateGpsLocation(
        location('2026-11-15T02:00:00.000Z'),
        location('invalid-date'),
        NOW
      )
    ).toBe(true)
  })
})

import {
  getGpsHealth,
  getGpsAge
} from '../app/utils/gps'

describe('GPS health', () => {
  const now = new Date('2026-11-15T10:00:00Z').getTime()

  it('returns live for GPS data up to 30 seconds old', () => {
    const recordedAt = new Date(now - 30_000).toISOString()

    expect(getGpsHealth(recordedAt, now)).toBe('live')
  })

  it('returns stale for GPS data older than 30 seconds', () => {
    const recordedAt = new Date(now - 60_000).toISOString()

    expect(getGpsHealth(recordedAt, now)).toBe('stale')
  })

  it('returns offline for GPS data older than 90 seconds', () => {
    const recordedAt = new Date(now - 91_000).toISOString()

    expect(getGpsHealth(recordedAt, now)).toBe('offline')
  })

  it('returns offline for invalid timestamps', () => {
    expect(getGpsHealth('invalid-date', now)).toBe('offline')
  })

  it('returns Invalid timestamp for invalid GPS age', () => {
    expect(getGpsAge('invalid-date', now)).toBe('Invalid timestamp')
  })

  it('formats GPS age in seconds', () => {
    const recordedAt = new Date(now - 15_000).toISOString()

    expect(getGpsAge(recordedAt, now)).toBe('15s ago')
  })

  it('formats GPS age in minutes', () => {
    const recordedAt = new Date(now - 120_000).toISOString()

    expect(getGpsAge(recordedAt, now)).toBe('2m ago')
  })
})