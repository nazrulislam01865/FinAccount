# Petrol/Octane Vehicle Fuel Preservation Fix

## Problem
The split migration marks the old `Petrol/Octane` master row inactive. Existing vehicle JSON still contains the fuel, but the vehicle editor previously rebuilt the dropdown from active fuel types only. The saved legacy value therefore appeared blank and could be overwritten.

## Fix
- Existing inactive fuel values are injected into that vehicle row only.
- The row is labelled `Legacy - select Petrol or Octane`.
- Backend validation accepts that legacy value only for the vehicle that already had it.
- New vehicles cannot select the inactive combined fuel.
- Update each legacy vehicle to exact `Petrol` or `Octane`, then save.

## Master-data workflow
Keep `Petrol` and `Octane` active. Keep `Petrol/Octane` inactive. Existing assigned vehicles remain visible and editable until converted.
