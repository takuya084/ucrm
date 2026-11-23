import test from 'node:test'
import assert from 'node:assert/strict'
import { groupTransportAssignments } from '../resources/js/lib/transportOverview.mjs'

const trip = { direction: 'pickup', scheduled_time: '14:30:00', location: '第一小学校', staff_id: 7, staff_name: '職員A', assistant_staff_id: 8, assistant_staff_name: '職員B' }
test('matching trips share one heading and retain each child and reservation', () => {
  const rows = [{ ...trip, child_id: 1, reservation_id: 11 }, { ...trip, scheduled_time: '14:30', child_id: 2, reservation_id: 12 }]
  const grouped = groupTransportAssignments(rows)
  assert.equal(grouped.pickup.length, 1)
  assert.equal(grouped.pickup[0].time, '14:30')
  assert.deepEqual(grouped.pickup[0].rows.map(row => row.reservation_id), [11, 12])
  assert.deepEqual(rows[0], { ...trip, child_id: 1, reservation_id: 11 })
})
test('direction, time, place and both staff identities prevent unrelated trips from merging', () => {
  const rows = [trip, { ...trip, direction: 'dropoff' }, { ...trip, scheduled_time: '15:00' }, { ...trip, location: '第二小学校' }, { ...trip, staff_id: 9 }, { ...trip, assistant_staff_id: 10 }, { ...trip, assistant_staff_id: null, assistant_staff_name: null }]
  const grouped = groupTransportAssignments(rows)
  assert.equal(grouped.pickup.length, 6)
  assert.equal(grouped.dropoff.length, 1)
})
test('trips are sorted by time with missing times last; empty directions remain available', () => {
  const grouped = groupTransportAssignments([{ ...trip, scheduled_time: null }, { ...trip, scheduled_time: '16:00' }, { ...trip, scheduled_time: '09:00' }])
  assert.deepEqual(grouped.pickup.map(row => row.time), ['09:00', '16:00', ''])
  assert.deepEqual(grouped.dropoff, [])
  assert.deepEqual(groupTransportAssignments(), { pickup: [], dropoff: [] })
})
