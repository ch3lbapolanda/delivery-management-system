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