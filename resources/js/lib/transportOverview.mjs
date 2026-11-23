// Merge only matching trips; both assignees are part of the group identity.
export function groupTransportAssignments(assignments = []) {
  const result = { pickup: [], dropoff: [] }
  for (const direction of Object.keys(result)) {
    const groups = new Map()
    for (const row of assignments.filter(row => row.direction === direction)) {
      const time = row.scheduled_time?.slice(0, 5) || ''
      const key = JSON.stringify([time, row.location || '', row.staff_id ?? null, row.staff_name || '', row.assistant_staff_id ?? null, row.assistant_staff_name || ''])
      if (!groups.has(key)) groups.set(key, { ...row, key, time, rows: [] })
      groups.get(key).rows.push(row)
    }
    result[direction] = [...groups.values()].sort((a, b) => (a.time || '99:99').localeCompare(b.time || '99:99') || (a.location || '').localeCompare(b.location || '', 'ja') || (a.staff_name || '').localeCompare(b.staff_name || '', 'ja'))
  }
  return result
}
