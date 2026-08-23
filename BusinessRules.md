# Smart City Ev Charging &amp; Parking Management
Business rules

Users & Vehicles
1. A User must register an account before booking any charging or parking slot.
2. A User can own one or more Vehicles, but each Vehicle belongs to exactly one User.
3. Each Vehicle has a specific battery capacity (kWh) and connector type (e.g., Type 2, CCS, CHAdeMO), which determines which stations it can use.

Stations & Slots
4. A Charging Station is located at one physical site and contains one or more Charging Slots (parking + charger units).
5. Each Charging Slot supports one or more connector types and has a maximum power output (kW).
6. A Slot can only be in one status at a time: Available, Reserved, Occupied, or Under Maintenance.

Reservations (Temporal Data)
7. A User can make a Reservation for a specific Slot for a specific future time window (start time, end time).
8. A Slot cannot have two overlapping Reservations — the system must reject any booking that conflicts with an existing time window on the same slot.
9. A Reservation automatically expires if the vehicle doesn't check in within a grace period (e.g., 15 minutes) after the start time, releasing the slot.
10. A Reservation must have a duration greater than 0 and less than a maximum allowed booking length (e.g., 8 hours).

Charging Sessions
11. A Charging Session starts only when a vehicle checks in at a reserved (or walk-in available) slot, and ends when the vehicle disconnects or the reserved time expires.
12. Each Charging Session records start time, end time, energy delivered (kWh), and the rate applied at that time.
13. A Charging Session's total cost = energy consumed (kWh) × the applicable rate(s) during that session — if the session spans a rate change (e.g., peak → off-peak), cost must be calculated proportionally per rate segment.

Dynamic Pricing
14. Each Station (or the whole city zone) has a Pricing Schedule defining rate per kWh for different time bands (e.g., peak, off-peak, weekend).
15. Only one price can be active for a given station at any given timestamp — pricing rules must not overlap.
16. Historical sessions must always reference the rate that was active at the time, not the current rate (rate history must be preserved for billing accuracy).

Payments
17. Every completed Charging Session generates exactly one Invoice/Payment record.
18. A User cannot start a new Reservation if they have unpaid/overdue invoices beyond a set limit (optional business constraint).