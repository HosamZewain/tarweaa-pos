# قاعدة بيانات نظام إدارة المطعم — التصميم الكامل
## Restaurant Management System — Full Database Schema (Step 1)

---

## مبادئ التصميم | Design Principles

- **Money**: `DECIMAL(12,2)` — never FLOAT
- **Quantities**: `DECIMAL(10,3)` — for fractional inventory (kg, liter)
- **Soft deletes**: `deleted_at` on all master data tables
- **Audit trail**: `created_by`, `updated_by` (FK → users.id) on all tables
- **Locale**: Arabic-first system; `name` fields store Arabic text
- **Engine**: MySQL 8.0+ (InnoDB), supports JSON columns and partial-index workarounds
- **One active drawer per cashier**: enforced via `cashier_active_sessions` guard table (PK = cashier_id)

---

## الجداول | Tables

---

## ═══════════════════════════════════════
## GROUP 1 — AUTH & ACCESS CONTROL
## ═══════════════════════════════════════

---

### 1. `users`

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| `name` | VARCHAR(255) | NOT NULL | Full name (Arabic) |
| `username` | VARCHAR(100) | NOT NULL, UNIQUE | Login username |
| `email` | VARCHAR(255) | NULLABLE, UNIQUE | Optional |
| `password` | VARCHAR(255) | NOT NULL | Bcrypt hash |
| `pin` | VARCHAR(6) | NULLABLE | Quick POS login PIN |
| `phone` | VARCHAR(20) | NULLABLE | |
| `is_active` | TINYINT(1) | NOT NULL, DEFAULT 1 | Soft enable/disable |
| `created_by` | BIGINT UNSIGNED | NULLABLE, FK → users.id | |
| `updated_by` | BIGINT UNSIGNED | NULLABLE, FK → users.id | |
| `created_at` | TIMESTAMP | NOT NULL, DEFAULT CURRENT | |
| `updated_at` | TIMESTAMP | NOT NULL, DEFAULT CURRENT | |
| `deleted_at` | TIMESTAMP | NULLABLE | Soft delete |

**Indexes:**
```
UNIQUE  idx_users_username    (username)
UNIQUE  idx_users_email       (email)
INDEX   idx_users_is_active   (is_active)
```

---

### 2. `roles`

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| `name` | VARCHAR(100) | NOT NULL, UNIQUE | e.g. admin, cashier, manager, kitchen |
| `display_name` | VARCHAR(255) | NOT NULL | Arabic label |
| `description` | TEXT | NULLABLE | |
| `is_active` | TINYINT(1) | NOT NULL, DEFAULT 1 | |
| `created_by` | BIGINT UNSIGNED | NULLABLE, FK → users.id | |
| `updated_by` | BIGINT UNSIGNED | NULLABLE, FK → users.id | |
| `created_at` | TIMESTAMP | NOT NULL | |
| `updated_at` | TIMESTAMP | NOT NULL | |

---

### 3. `permissions`

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| `name` | VARCHAR(150) | NOT NULL, UNIQUE | e.g. orders.create, reports.view |
| `display_name` | VARCHAR(255) | NOT NULL | Arabic label |
| `group` | VARCHAR(100) | NOT NULL | UI grouping: orders, inventory, reports... |
| `created_at` | TIMESTAMP | NOT NULL | |
| `updated_at` | TIMESTAMP | NOT NULL | |

**Indexes:**
```
INDEX   idx_permissions_group  (group)
```

---

### 4. `role_permissions` *(pivot)*

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `role_id` | BIGINT UNSIGNED | NOT NULL, FK → roles.id | |
| `permission_id` | BIGINT UNSIGNED | NOT NULL, FK → permissions.id | |

**Indexes:**
```
PRIMARY KEY  (role_id, permission_id)
INDEX        idx_rp_permission_id  (permission_id)
```

---

### 5. `user_roles` *(pivot)*

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `user_id` | BIGINT UNSIGNED | NOT NULL, FK → users.id | |
| `role_id` | BIGINT UNSIGNED | NOT NULL, FK → roles.id | |
| `assigned_at` | TIMESTAMP | NOT NULL, DEFAULT CURRENT | |
| `assigned_by` | BIGINT UNSIGNED | NULLABLE, FK → users.id | |

**Indexes:**
```
PRIMARY KEY  (user_id, role_id)
INDEX        idx_ur_role_id  (role_id)
```

---

## ═══════════════════════════════════════
## GROUP 2 — OPERATIONS (SHIFT & DRAWER)
## ═══════════════════════════════════════

---

### 6. `pos_devices`

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| `name` | VARCHAR(100) | NOT NULL | e.g. "POS-01", "كاشير المدخل" |
| `identifier` | VARCHAR(100) | NOT NULL, UNIQUE | MAC address or UUID |
| `location` | VARCHAR(255) | NULLABLE | Physical location description |
| `is_active` | TINYINT(1) | NOT NULL, DEFAULT 1 | |
| `last_seen_at` | TIMESTAMP | NULLABLE | Heartbeat timestamp |
| `created_by` | BIGINT UNSIGNED | NULLABLE, FK → users.id | |
| `updated_by` | BIGINT UNSIGNED | NULLABLE, FK → users.id | |
| `created_at` | TIMESTAMP | NOT NULL | |
| `updated_at` | TIMESTAMP | NOT NULL | |

---

### 7. `shifts`

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| `shift_number` | VARCHAR(50) | NOT NULL, UNIQUE | Auto-generated: SHF-20260325-001 |
| `status` | ENUM('open','closed') | NOT NULL, DEFAULT 'open' | |
| `opened_by` | BIGINT UNSIGNED | NOT NULL, FK → users.id | Manager who opens shift |
| `closed_by` | BIGINT UNSIGNED | NULLABLE, FK → users.id | |
| `started_at` | TIMESTAMP | NOT NULL | |
| `ended_at` | TIMESTAMP | NULLABLE | |
| `notes` | TEXT | NULLABLE | |
| `expected_cash` | DECIMAL(12,2) | NULLABLE | System-calculated at close |
| `actual_cash` | DECIMAL(12,2) | NULLABLE | Manager-entered at close |
| `cash_difference` | DECIMAL(12,2) | NULLABLE | actual - expected |
| `created_by` | BIGINT UNSIGNED | NULLABLE, FK → users.id | |
| `updated_by` | BIGINT UNSIGNED | NULLABLE, FK → users.id | |
| `created_at` | TIMESTAMP | NOT NULL | |
| `updated_at` | TIMESTAMP | NOT NULL | |

**Indexes:**
```
UNIQUE  idx_shifts_number      (shift_number)
INDEX   idx_shifts_status      (status)
INDEX   idx_shifts_started_at  (started_at)
```

**Business Rule:** Shift cannot close while any `cashier_drawer_sessions.status = 'open'` references this `shift_id`. Enforced at application layer + DB-level via FK + query guard.

---

### 8. `cashier_drawer_sessions`

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| `session_number` | VARCHAR(50) | NOT NULL, UNIQUE | Auto-generated: DRW-20260325-001 |
| `cashier_id` | BIGINT UNSIGNED | NOT NULL, FK → users.id | The cashier who owns this drawer |
| `shift_id` | BIGINT UNSIGNED | NOT NULL, FK → shifts.id | Must be an open shift |
| `pos_device_id` | BIGINT UNSIGNED | NOT NULL, FK → pos_devices.id | Which POS terminal |
| `opened_by` | BIGINT UNSIGNED | NOT NULL, FK → users.id | Could be manager opening for cashier |
| `closed_by` | BIGINT UNSIGNED | NULLABLE, FK → users.id | |
| `opening_balance` | DECIMAL(12,2) | NOT NULL, DEFAULT 0.00 | Float given to cashier |
| `closing_balance` | DECIMAL(12,2) | NULLABLE | Actual cash counted at close |
| `expected_balance` | DECIMAL(12,2) | NULLABLE | System-calculated |
| `cash_difference` | DECIMAL(12,2) | NULLABLE | closing - expected |
| `status` | ENUM('open','closed') | NOT NULL, DEFAULT 'open' | |
| `started_at` | TIMESTAMP | NOT NULL | |
| `ended_at` | TIMESTAMP | NULLABLE | |
| `notes` | TEXT | NULLABLE | |
| `created_by` | BIGINT UNSIGNED | NULLABLE, FK → users.id | |
| `updated_by` | BIGINT UNSIGNED | NULLABLE, FK → users.id | |
| `created_at` | TIMESTAMP | NOT NULL | |
| `updated_at` | TIMESTAMP | NOT NULL | |

**Indexes:**
```
UNIQUE  idx_drawer_session_number   (session_number)
INDEX   idx_drawer_cashier_id       (cashier_id)
INDEX   idx_drawer_shift_id         (shift_id)
INDEX   idx_drawer_status           (status)
INDEX   idx_drawer_pos_device_id    (pos_device_id)
```

---

### 9. `cashier_active_sessions` *(DB-Level Guard)*

> **Purpose:** Prevents multiple open drawer sessions per cashier at the database level.
> When a drawer opens → INSERT here. When it closes → DELETE here.
> The `cashier_id` as PRIMARY KEY makes it impossible to insert a second open session.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `cashier_id` | BIGINT UNSIGNED | **PRIMARY KEY**, FK → users.id | One row = one open session |
| `drawer_session_id` | BIGINT UNSIGNED | NOT NULL, UNIQUE, FK → cashier_drawer_sessions.id | |
| `pos_device_id` | BIGINT UNSIGNED | NOT NULL, FK → pos_devices.id | |
| `shift_id` | BIGINT UNSIGNED | NOT NULL, FK → shifts.id | |
| `created_at` | TIMESTAMP | NOT NULL | |

**Constraint Logic:**
- `INSERT INTO cashier_active_sessions` → succeeds only if cashier has no open session (PK violation otherwise)
- `DELETE FROM cashier_active_sessions WHERE cashier_id = ?` → on session close

---

### 10. `cash_movements`

> Tracks every cash event inside a drawer session: sales, refunds, cash-in, cash-out.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| `drawer_session_id` | BIGINT UNSIGNED | NOT NULL, FK → cashier_drawer_sessions.id | |
| `shift_id` | BIGINT UNSIGNED | NOT NULL, FK → shifts.id | Denormalized for reporting |
| `cashier_id` | BIGINT UNSIGNED | NOT NULL, FK → users.id | Denormalized for reporting |
| `type` | ENUM('opening','sale','refund','cash_in','cash_out','closing') | NOT NULL | |
| `direction` | ENUM('in','out') | NOT NULL | Computed from type for safety |
| `amount` | DECIMAL(12,2) | NOT NULL | Always positive |
| `reference_type` | VARCHAR(100) | NULLABLE | 'order', 'expense', 'manual' |
| `reference_id` | BIGINT UNSIGNED | NULLABLE | Polymorphic reference |
| `notes` | TEXT | NULLABLE | |
| `performed_by` | BIGINT UNSIGNED | NOT NULL, FK → users.id | Who initiated |
| `created_by` | BIGINT UNSIGNED | NULLABLE, FK → users.id | |
| `updated_by` | BIGINT UNSIGNED | NULLABLE, FK → users.id | |
| `created_at` | TIMESTAMP | NOT NULL | |
| `updated_at` | TIMESTAMP | NOT NULL | |

**Indexes:**
```
INDEX   idx_cm_drawer_session_id          (drawer_session_id)
INDEX   idx_cm_shift_id                   (shift_id)
INDEX   idx_cm_cashier_id                 (cashier_id)
INDEX   idx_cm_type                       (type)
INDEX   idx_cm_reference                  (reference_type, reference_id)
```

---

## ═══════════════════════════════════════
## GROUP 3 — CUSTOMERS & ORDERS
## ═══════════════════════════════════════

---

### 11. `customers`

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| `name` | VARCHAR(255) | NOT NULL | |
| `phone` | VARCHAR(20) | NOT NULL, UNIQUE | Primary lookup key |
| `email` | VARCHAR(255) | NULLABLE | |
| `address` | TEXT | NULLABLE | Default delivery address |
| `notes` | TEXT | NULLABLE | Allergies, preferences |
| `loyalty_points` | INT UNSIGNED | NOT NULL, DEFAULT 0 | |
| `total_orders` | INT UNSIGNED | NOT NULL, DEFAULT 0 | Denormalized counter |
| `total_spent` | DECIMAL(12,2) | NOT NULL, DEFAULT 0.00 | Denormalized |
| `is_active` | TINYINT(1) | NOT NULL, DEFAULT 1 | |
| `created_by` | BIGINT UNSIGNED | NULLABLE, FK → users.id | |
| `updated_by` | BIGINT UNSIGNED | NULLABLE, FK → users.id | |
| `created_at` | TIMESTAMP | NOT NULL | |
| `updated_at` | TIMESTAMP | NOT NULL | |
| `deleted_at` | TIMESTAMP | NULLABLE | |

**Indexes:**
```
UNIQUE  idx_customers_phone   (phone)
INDEX   idx_customers_name    (name)
```

---

### 12. `orders`

> Core transactional table. Every order MUST link to shift + cashier + drawer + pos_device.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| `order_number` | VARCHAR(50) | NOT NULL, UNIQUE | e.g. ORD-20260325-0042 |
| `type` | ENUM('takeaway','pickup','delivery') | NOT NULL | |
| `status` | ENUM('pending','confirmed','preparing','ready','dispatched','delivered','cancelled','refunded') | NOT NULL, DEFAULT 'pending' | |
| `source` | ENUM('pos','talabat','jahez','hungerstation','other') | NOT NULL, DEFAULT 'pos' | Order origin |
| `cashier_id` | BIGINT UNSIGNED | NOT NULL, FK → users.id | **REQUIRED** |
| `shift_id` | BIGINT UNSIGNED | NOT NULL, FK → shifts.id | **REQUIRED** |
| `drawer_session_id` | BIGINT UNSIGNED | NOT NULL, FK → cashier_drawer_sessions.id | **REQUIRED** |
| `pos_device_id` | BIGINT UNSIGNED | NOT NULL, FK → pos_devices.id | **REQUIRED** |
| `customer_id` | BIGINT UNSIGNED | NULLABLE, FK → customers.id | Known customer |
| `customer_name` | VARCHAR(255) | NULLABLE | Walk-in / external snapshot |
| `customer_phone` | VARCHAR(20) | NULLABLE | Walk-in / external snapshot |
| `delivery_address` | TEXT | NULLABLE | For delivery orders |
| `subtotal` | DECIMAL(12,2) | NOT NULL, DEFAULT 0.00 | Sum of items before discount/tax |
| `discount_type` | ENUM('fixed','percentage') | NULLABLE | |
| `discount_value` | DECIMAL(12,2) | NOT NULL, DEFAULT 0.00 | Percentage or fixed amount |
| `discount_amount` | DECIMAL(12,2) | NOT NULL, DEFAULT 0.00 | Computed discount in money |
| `tax_rate` | DECIMAL(5,2) | NOT NULL, DEFAULT 0.00 | Percentage (e.g. 15.00) |
| `tax_amount` | DECIMAL(12,2) | NOT NULL, DEFAULT 0.00 | |
| `delivery_fee` | DECIMAL(12,2) | NOT NULL, DEFAULT 0.00 | |
| `total` | DECIMAL(12,2) | NOT NULL, DEFAULT 0.00 | Final amount due |
| `payment_status` | ENUM('unpaid','paid','partial','refunded') | NOT NULL, DEFAULT 'unpaid' | |
| `paid_amount` | DECIMAL(12,2) | NOT NULL, DEFAULT 0.00 | Total received |
| `change_amount` | DECIMAL(12,2) | NOT NULL, DEFAULT 0.00 | Change given back |
| `refund_amount` | DECIMAL(12,2) | NOT NULL, DEFAULT 0.00 | |
| `refund_reason` | TEXT | NULLABLE | |
| `refunded_by` | BIGINT UNSIGNED | NULLABLE, FK → users.id | |
| `refunded_at` | TIMESTAMP | NULLABLE | |
| `external_order_id` | VARCHAR(255) | NULLABLE | Talabat/aggregator order ID |
| `external_order_number` | VARCHAR(255) | NULLABLE | |
| `notes` | TEXT | NULLABLE | |
| `scheduled_at` | TIMESTAMP | NULLABLE | Future/pre-order |
| `confirmed_at` | TIMESTAMP | NULLABLE | |
| `ready_at` | TIMESTAMP | NULLABLE | |
| `dispatched_at` | TIMESTAMP | NULLABLE | |
| `delivered_at` | TIMESTAMP | NULLABLE | |
| `cancelled_at` | TIMESTAMP | NULLABLE | |
| `cancelled_by` | BIGINT UNSIGNED | NULLABLE, FK → users.id | |
| `cancellation_reason` | TEXT | NULLABLE | |
| `created_by` | BIGINT UNSIGNED | NULLABLE, FK → users.id | |
| `updated_by` | BIGINT UNSIGNED | NULLABLE, FK → users.id | |
| `created_at` | TIMESTAMP | NOT NULL | |
| `updated_at` | TIMESTAMP | NOT NULL | |
| `deleted_at` | TIMESTAMP | NULLABLE | |

**Indexes:**
```
UNIQUE  idx_orders_order_number          (order_number)
INDEX   idx_orders_cashier_id            (cashier_id)
INDEX   idx_orders_shift_id              (shift_id)
INDEX   idx_orders_drawer_session_id     (drawer_session_id)
INDEX   idx_orders_pos_device_id         (pos_device_id)
INDEX   idx_orders_status                (status)
INDEX   idx_orders_type                  (type)
INDEX   idx_orders_source                (source)
INDEX   idx_orders_customer_id           (customer_id)
INDEX   idx_orders_payment_status        (payment_status)
INDEX   idx_orders_created_at            (created_at)
INDEX   idx_orders_external_order_id     (external_order_id)
```

---

### 13. `order_payments`

> Supports split payments (cash + card, etc.). One order can have multiple payment rows.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| `order_id` | BIGINT UNSIGNED | NOT NULL, FK → orders.id | |
| `payment_method` | ENUM('cash','card','online','talabat_pay','jahez_pay','other') | NOT NULL | |
| `amount` | DECIMAL(12,2) | NOT NULL | |
| `reference_number` | VARCHAR(255) | NULLABLE | Card terminal ref, online TX id |
| `notes` | TEXT | NULLABLE | |
| `created_by` | BIGINT UNSIGNED | NULLABLE, FK → users.id | |
| `created_at` | TIMESTAMP | NOT NULL | |
| `updated_at` | TIMESTAMP | NOT NULL | |

**Indexes:**
```
INDEX   idx_op_order_id          (order_id)
INDEX   idx_op_payment_method    (payment_method)
```

---

### 14. `order_items`

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| `order_id` | BIGINT UNSIGNED | NOT NULL, FK → orders.id | |
| `menu_item_id` | BIGINT UNSIGNED | NOT NULL, FK → menu_items.id | |
| `menu_item_variant_id` | BIGINT UNSIGNED | NULLABLE, FK → menu_item_variants.id | |
| `item_name` | VARCHAR(255) | NOT NULL | **Snapshot** at time of order |
| `variant_name` | VARCHAR(255) | NULLABLE | **Snapshot** |
| `unit_price` | DECIMAL(12,2) | NOT NULL | **Snapshot** |
| `cost_price` | DECIMAL(12,2) | NULLABLE | **Snapshot** for margin reports |
| `quantity` | INT UNSIGNED | NOT NULL, DEFAULT 1 | |
| `discount_amount` | DECIMAL(12,2) | NOT NULL, DEFAULT 0.00 | Item-level discount |
| `total` | DECIMAL(12,2) | NOT NULL | (unit_price * quantity) - discount |
| `status` | ENUM('pending','preparing','ready','cancelled') | NOT NULL, DEFAULT 'pending' | Kitchen status |
| `notes` | TEXT | NULLABLE | e.g. "بدون بصل" |
| `created_by` | BIGINT UNSIGNED | NULLABLE, FK → users.id | |
| `updated_by` | BIGINT UNSIGNED | NULLABLE, FK → users.id | |
| `created_at` | TIMESTAMP | NOT NULL | |
| `updated_at` | TIMESTAMP | NOT NULL | |

**Indexes:**
```
INDEX   idx_oi_order_id              (order_id)
INDEX   idx_oi_menu_item_id          (menu_item_id)
INDEX   idx_oi_status                (status)
```

---

### 15. `order_item_modifiers`

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| `order_item_id` | BIGINT UNSIGNED | NOT NULL, FK → order_items.id | |
| `menu_item_modifier_id` | BIGINT UNSIGNED | NOT NULL, FK → menu_item_modifiers.id | |
| `modifier_name` | VARCHAR(255) | NOT NULL | **Snapshot** |
| `price` | DECIMAL(12,2) | NOT NULL, DEFAULT 0.00 | **Snapshot** |
| `quantity` | INT UNSIGNED | NOT NULL, DEFAULT 1 | |
| `created_at` | TIMESTAMP | NOT NULL | |
| `updated_at` | TIMESTAMP | NOT NULL | |

**Indexes:**
```
INDEX   idx_oim_order_item_id   (order_item_id)
```

---

## ═══════════════════════════════════════
## GROUP 4 — MENU
## ═══════════════════════════════════════

---

### 16. `menu_categories`

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| `parent_id` | BIGINT UNSIGNED | NULLABLE, FK → menu_categories.id | For subcategories |
| `name` | VARCHAR(255) | NOT NULL | Arabic name |
| `description` | TEXT | NULLABLE | |
| `image` | VARCHAR(500) | NULLABLE | Path or URL |
| `sort_order` | SMALLINT UNSIGNED | NOT NULL, DEFAULT 0 | |
| `is_active` | TINYINT(1) | NOT NULL, DEFAULT 1 | |
| `created_by` | BIGINT UNSIGNED | NULLABLE, FK → users.id | |
| `updated_by` | BIGINT UNSIGNED | NULLABLE, FK → users.id | |
| `created_at` | TIMESTAMP | NOT NULL | |
| `updated_at` | TIMESTAMP | NOT NULL | |
| `deleted_at` | TIMESTAMP | NULLABLE | |

**Indexes:**
```
INDEX   idx_mc_parent_id    (parent_id)
INDEX   idx_mc_sort_order   (sort_order)
INDEX   idx_mc_is_active    (is_active)
```

---

### 17. `menu_items`

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| `category_id` | BIGINT UNSIGNED | NOT NULL, FK → menu_categories.id | |
| `name` | VARCHAR(255) | NOT NULL | Arabic name |
| `description` | TEXT | NULLABLE | |
| `sku` | VARCHAR(100) | NULLABLE, UNIQUE | Internal code |
| `image` | VARCHAR(500) | NULLABLE | |
| `type` | ENUM('simple','variable') | NOT NULL, DEFAULT 'simple' | simple=fixed price, variable=has variants |
| `base_price` | DECIMAL(12,2) | NOT NULL, DEFAULT 0.00 | Used when type=simple |
| `cost_price` | DECIMAL(12,2) | NULLABLE | For margin calculation |
| `preparation_time` | SMALLINT UNSIGNED | NULLABLE | In minutes |
| `track_inventory` | TINYINT(1) | NOT NULL, DEFAULT 0 | Link to inventory deduction |
| `is_available` | TINYINT(1) | NOT NULL, DEFAULT 1 | Real-time availability toggle |
| `is_active` | TINYINT(1) | NOT NULL, DEFAULT 1 | Master active/inactive |
| `sort_order` | SMALLINT UNSIGNED | NOT NULL, DEFAULT 0 | |
| `created_by` | BIGINT UNSIGNED | NULLABLE, FK → users.id | |
| `updated_by` | BIGINT UNSIGNED | NULLABLE, FK → users.id | |
| `created_at` | TIMESTAMP | NOT NULL | |
| `updated_at` | TIMESTAMP | NOT NULL | |
| `deleted_at` | TIMESTAMP | NULLABLE | |

**Indexes:**
```
UNIQUE  idx_mi_sku           (sku)
INDEX   idx_mi_category_id   (category_id)
INDEX   idx_mi_is_available  (is_available)
INDEX   idx_mi_is_active     (is_active)
INDEX   idx_mi_type          (type)
```

---

### 18. `menu_item_variants`

> Used when `menu_items.type = 'variable'` (e.g., Small / Medium / Large).

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| `menu_item_id` | BIGINT UNSIGNED | NOT NULL, FK → menu_items.id | |
| `name` | VARCHAR(255) | NOT NULL | e.g. "كبير", "وسط", "صغير" |
| `sku` | VARCHAR(100) | NULLABLE | |
| `price` | DECIMAL(12,2) | NOT NULL | Overrides base_price |
| `cost_price` | DECIMAL(12,2) | NULLABLE | |
| `is_available` | TINYINT(1) | NOT NULL, DEFAULT 1 | |
| `sort_order` | SMALLINT UNSIGNED | NOT NULL, DEFAULT 0 | |
| `created_by` | BIGINT UNSIGNED | NULLABLE, FK → users.id | |
| `updated_by` | BIGINT UNSIGNED | NULLABLE, FK → users.id | |
| `created_at` | TIMESTAMP | NOT NULL | |
| `updated_at` | TIMESTAMP | NOT NULL | |

**Indexes:**
```
INDEX   idx_miv_menu_item_id    (menu_item_id)
INDEX   idx_miv_is_available    (is_available)
```

---

### 19. `modifier_groups`

> Groups of modifiers (e.g., "الإضافات", "درجة الحرارة", "الصلصات").

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| `name` | VARCHAR(255) | NOT NULL | Arabic group name |
| `selection_type` | ENUM('single','multiple') | NOT NULL, DEFAULT 'multiple' | Radio vs checkbox |
| `is_required` | TINYINT(1) | NOT NULL, DEFAULT 0 | Mandatory selection |
| `min_selections` | TINYINT UNSIGNED | NOT NULL, DEFAULT 0 | |
| `max_selections` | TINYINT UNSIGNED | NULLABLE | NULL = unlimited |
| `sort_order` | SMALLINT UNSIGNED | NOT NULL, DEFAULT 0 | |
| `is_active` | TINYINT(1) | NOT NULL, DEFAULT 1 | |
| `created_by` | BIGINT UNSIGNED | NULLABLE, FK → users.id | |
| `updated_by` | BIGINT UNSIGNED | NULLABLE, FK → users.id | |
| `created_at` | TIMESTAMP | NOT NULL | |
| `updated_at` | TIMESTAMP | NOT NULL | |

---

### 20. `menu_item_modifier_groups` *(pivot)*

> Links which modifier groups apply to which menu items.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `menu_item_id` | BIGINT UNSIGNED | NOT NULL, FK → menu_items.id | |
| `modifier_group_id` | BIGINT UNSIGNED | NOT NULL, FK → modifier_groups.id | |
| `sort_order` | SMALLINT UNSIGNED | NOT NULL, DEFAULT 0 | |

**Indexes:**
```
PRIMARY KEY  (menu_item_id, modifier_group_id)
INDEX        idx_mimf_modifier_group_id  (modifier_group_id)
```

---

### 21. `menu_item_modifiers`

> Individual modifier options within a group (e.g., "جبنة إضافية", "بدون بصل").

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| `modifier_group_id` | BIGINT UNSIGNED | NOT NULL, FK → modifier_groups.id | |
| `name` | VARCHAR(255) | NOT NULL | Arabic name |
| `price` | DECIMAL(12,2) | NOT NULL, DEFAULT 0.00 | Additional charge |
| `cost_price` | DECIMAL(12,2) | NOT NULL, DEFAULT 0.00 | |
| `is_available` | TINYINT(1) | NOT NULL, DEFAULT 1 | |
| `sort_order` | SMALLINT UNSIGNED | NOT NULL, DEFAULT 0 | |
| `created_by` | BIGINT UNSIGNED | NULLABLE, FK → users.id | |
| `updated_by` | BIGINT UNSIGNED | NULLABLE, FK → users.id | |
| `created_at` | TIMESTAMP | NOT NULL | |
| `updated_at` | TIMESTAMP | NOT NULL | |

**Indexes:**
```
INDEX   idx_mim_modifier_group_id   (modifier_group_id)
INDEX   idx_mim_is_available        (is_available)
```

---

## ═══════════════════════════════════════
## GROUP 5 — INVENTORY & PURCHASING
## ═══════════════════════════════════════

---

### 22. `suppliers`

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| `name` | VARCHAR(255) | NOT NULL | Arabic name |
| `contact_person` | VARCHAR(255) | NULLABLE | |
| `phone` | VARCHAR(20) | NULLABLE | |
| `email` | VARCHAR(255) | NULLABLE | |
| `address` | TEXT | NULLABLE | |
| `tax_number` | VARCHAR(100) | NULLABLE | VAT registration |
| `payment_terms` | VARCHAR(255) | NULLABLE | e.g. "30 يوم من الفاتورة" |
| `notes` | TEXT | NULLABLE | |
| `is_active` | TINYINT(1) | NOT NULL, DEFAULT 1 | |
| `created_by` | BIGINT UNSIGNED | NULLABLE, FK → users.id | |
| `updated_by` | BIGINT UNSIGNED | NULLABLE, FK → users.id | |
| `created_at` | TIMESTAMP | NOT NULL | |
| `updated_at` | TIMESTAMP | NOT NULL | |
| `deleted_at` | TIMESTAMP | NULLABLE | |

---

### 23. `inventory_items`

> Raw ingredients and stockable materials. Separate from menu items.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| `name` | VARCHAR(255) | NOT NULL | Arabic name |
| `sku` | VARCHAR(100) | NULLABLE, UNIQUE | |
| `category` | VARCHAR(100) | NULLABLE | e.g. لحوم، خضروات، مشروبات |
| `unit` | VARCHAR(50) | NOT NULL | kg, liter, piece, box, gram |
| `unit_cost` | DECIMAL(12,2) | NULLABLE | Latest purchase cost |
| `current_stock` | DECIMAL(10,3) | NOT NULL, DEFAULT 0.000 | Running balance |
| `minimum_stock` | DECIMAL(10,3) | NOT NULL, DEFAULT 0.000 | Low stock alert threshold |
| `maximum_stock` | DECIMAL(10,3) | NULLABLE | Capacity / reorder ceiling |
| `default_supplier_id` | BIGINT UNSIGNED | NULLABLE, FK → suppliers.id | |
| `is_active` | TINYINT(1) | NOT NULL, DEFAULT 1 | |
| `notes` | TEXT | NULLABLE | |
| `created_by` | BIGINT UNSIGNED | NULLABLE, FK → users.id | |
| `updated_by` | BIGINT UNSIGNED | NULLABLE, FK → users.id | |
| `created_at` | TIMESTAMP | NOT NULL | |
| `updated_at` | TIMESTAMP | NOT NULL | |
| `deleted_at` | TIMESTAMP | NULLABLE | |

**Indexes:**
```
UNIQUE  idx_inv_sku             (sku)
INDEX   idx_inv_category        (category)
INDEX   idx_inv_current_stock   (current_stock)
```

---

### 24. `inventory_transactions`

> Every stock movement. Immutable log — never update, only insert.

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| `inventory_item_id` | BIGINT UNSIGNED | NOT NULL, FK → inventory_items.id | |
| `type` | ENUM('purchase','sale_deduction','adjustment','waste','return','transfer_in','transfer_out') | NOT NULL | |
| `quantity` | DECIMAL(10,3) | NOT NULL | Can be negative for out-movements |
| `quantity_before` | DECIMAL(10,3) | NOT NULL | Snapshot before transaction |
| `quantity_after` | DECIMAL(10,3) | NOT NULL | Snapshot after transaction |
| `unit_cost` | DECIMAL(12,2) | NULLABLE | Cost per unit at time of transaction |
| `total_cost` | DECIMAL(12,2) | NULLABLE | quantity × unit_cost |
| `reference_type` | VARCHAR(100) | NULLABLE | 'purchase', 'order', 'manual' |
| `reference_id` | BIGINT UNSIGNED | NULLABLE | Polymorphic |
| `notes` | TEXT | NULLABLE | |
| `performed_by` | BIGINT UNSIGNED | NOT NULL, FK → users.id | |
| `created_by` | BIGINT UNSIGNED | NULLABLE, FK → users.id | |
| `created_at` | TIMESTAMP | NOT NULL | |
| `updated_at` | TIMESTAMP | NOT NULL | |

**Indexes:**
```
INDEX   idx_it_inventory_item_id     (inventory_item_id)
INDEX   idx_it_type                  (type)
INDEX   idx_it_reference             (reference_type, reference_id)
INDEX   idx_it_created_at            (created_at)
```

---

### 25. `purchases`

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| `purchase_number` | VARCHAR(50) | NOT NULL, UNIQUE | e.g. PO-20260325-001 |
| `supplier_id` | BIGINT UNSIGNED | NOT NULL, FK → suppliers.id | |
| `status` | ENUM('draft','ordered','received','partially_received','cancelled') | NOT NULL, DEFAULT 'draft' | |
| `invoice_number` | VARCHAR(100) | NULLABLE | Supplier invoice ref |
| `invoice_date` | DATE | NULLABLE | |
| `subtotal` | DECIMAL(12,2) | NOT NULL, DEFAULT 0.00 | |
| `tax_amount` | DECIMAL(12,2) | NOT NULL, DEFAULT 0.00 | |
| `discount_amount` | DECIMAL(12,2) | NOT NULL, DEFAULT 0.00 | |
| `total` | DECIMAL(12,2) | NOT NULL, DEFAULT 0.00 | |
| `paid_amount` | DECIMAL(12,2) | NOT NULL, DEFAULT 0.00 | |
| `payment_status` | ENUM('unpaid','partial','paid') | NOT NULL, DEFAULT 'unpaid' | |
| `payment_method` | VARCHAR(100) | NULLABLE | cash, bank_transfer, check |
| `received_at` | TIMESTAMP | NULLABLE | When goods received |
| `notes` | TEXT | NULLABLE | |
| `created_by` | BIGINT UNSIGNED | NULLABLE, FK → users.id | |
| `updated_by` | BIGINT UNSIGNED | NULLABLE, FK → users.id | |
| `created_at` | TIMESTAMP | NOT NULL | |
| `updated_at` | TIMESTAMP | NOT NULL | |
| `deleted_at` | TIMESTAMP | NULLABLE | |

**Indexes:**
```
UNIQUE  idx_po_purchase_number    (purchase_number)
INDEX   idx_po_supplier_id        (supplier_id)
INDEX   idx_po_status             (status)
INDEX   idx_po_payment_status     (payment_status)
```

---

### 26. `purchase_items`

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| `purchase_id` | BIGINT UNSIGNED | NOT NULL, FK → purchases.id | |
| `inventory_item_id` | BIGINT UNSIGNED | NOT NULL, FK → inventory_items.id | |
| `unit` | VARCHAR(50) | NOT NULL | Snapshot of unit at purchase time |
| `unit_price` | DECIMAL(12,2) | NOT NULL | |
| `quantity_ordered` | DECIMAL(10,3) | NOT NULL | |
| `quantity_received` | DECIMAL(10,3) | NOT NULL, DEFAULT 0.000 | Updated on receipt |
| `total` | DECIMAL(12,2) | NOT NULL | unit_price × quantity_ordered |
| `notes` | TEXT | NULLABLE | |
| `created_at` | TIMESTAMP | NOT NULL | |
| `updated_at` | TIMESTAMP | NOT NULL | |

**Indexes:**
```
INDEX   idx_pi_purchase_id         (purchase_id)
INDEX   idx_pi_inventory_item_id   (inventory_item_id)
```

---

## ═══════════════════════════════════════
## GROUP 6 — FINANCIAL (EXPENSES)
## ═══════════════════════════════════════

---

### 27. `expense_categories`

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| `name` | VARCHAR(255) | NOT NULL | Arabic name |
| `description` | TEXT | NULLABLE | |
| `is_active` | TINYINT(1) | NOT NULL, DEFAULT 1 | |
| `created_by` | BIGINT UNSIGNED | NULLABLE, FK → users.id | |
| `updated_by` | BIGINT UNSIGNED | NULLABLE, FK → users.id | |
| `created_at` | TIMESTAMP | NOT NULL | |
| `updated_at` | TIMESTAMP | NOT NULL | |

---

### 28. `expenses`

| Column | Type | Constraints | Notes |
|---|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| `expense_number` | VARCHAR(50) | NOT NULL, UNIQUE | e.g. EXP-20260325-001 |
| `category_id` | BIGINT UNSIGNED | NOT NULL, FK → expense_categories.id | |
| `shift_id` | BIGINT UNSIGNED | NULLABLE, FK → shifts.id | Linked to shift if cash-based |
| `drawer_session_id` | BIGINT UNSIGNED | NULLABLE, FK → cashier_drawer_sessions.id | Linked if paid from drawer |
| `amount` | DECIMAL(12,2) | NOT NULL | |
| `description` | TEXT | NOT NULL | What was purchased/paid |
| `payment_method` | ENUM('cash','card','bank_transfer') | NOT NULL | |
| `receipt_number` | VARCHAR(100) | NULLABLE | External receipt ref |
| `expense_date` | DATE | NOT NULL | |
| `approved_by` | BIGINT UNSIGNED | NULLABLE, FK → users.id | Manager approval |
| `approved_at` | TIMESTAMP | NULLABLE | |
| `notes` | TEXT | NULLABLE | |
| `created_by` | BIGINT UNSIGNED | NULLABLE, FK → users.id | |
| `updated_by` | BIGINT UNSIGNED | NULLABLE, FK → users.id | |
| `created_at` | TIMESTAMP | NOT NULL | |
| `updated_at` | TIMESTAMP | NOT NULL | |
| `deleted_at` | TIMESTAMP | NULLABLE | |

**Indexes:**
```
UNIQUE  idx_exp_expense_number   (expense_number)
INDEX   idx_exp_category_id      (category_id)
INDEX   idx_exp_shift_id         (shift_id)
INDEX   idx_exp_expense_date     (expense_date)
INDEX   idx_exp_payment_method   (payment_method)
```

---

## ═══════════════════════════════════════
## RELATIONSHIPS MAP
## ═══════════════════════════════════════

```
users ──< user_roles >── roles ──< role_permissions >── permissions

users ──────────────────── shifts (opened_by, closed_by)
users ──────────────────── pos_devices (created_by)
users ──────────────────── cashier_drawer_sessions (cashier_id, opened_by, closed_by)
cashier_active_sessions ─── cashier_drawer_sessions (1:1 guard)
shifts ──────────────────── cashier_drawer_sessions (1:many)
pos_devices ─────────────── cashier_drawer_sessions (1:many)

cashier_drawer_sessions ─── cash_movements (1:many)
cashier_drawer_sessions ─── orders (1:many)
shifts ──────────────────── orders (1:many)
pos_devices ─────────────── orders (1:many)
users ──────────────────── orders (cashier_id)
customers ───────────────── orders (0..1:many)

orders ──────────────────── order_items (1:many)
orders ──────────────────── order_payments (1:many)
order_items ─────────────── order_item_modifiers (1:many)

menu_categories ─────────── menu_categories (self, parent_id)
menu_categories ─────────── menu_items (1:many)
menu_items ──────────────── menu_item_variants (1:many)
menu_items ──────────────── menu_item_modifier_groups (pivot)
modifier_groups ─────────── menu_item_modifier_groups (pivot)
modifier_groups ─────────── menu_item_modifiers (1:many)

menu_items ──────────────── order_items (1:many, snapshot)
menu_item_variants ──────── order_items (0..1:many, snapshot)
menu_item_modifiers ─────── order_item_modifiers (1:many, snapshot)

suppliers ───────────────── inventory_items (default_supplier_id)
suppliers ───────────────── purchases (1:many)
purchases ───────────────── purchase_items (1:many)
inventory_items ─────────── purchase_items (1:many)
inventory_items ─────────── inventory_transactions (1:many)

expense_categories ──────── expenses (1:many)
shifts ──────────────────── expenses (0..1:many)
cashier_drawer_sessions ─── expenses (0..1:many)
```

---

## ═══════════════════════════════════════
## ENUM CONSTANTS REFERENCE
## ═══════════════════════════════════════

### Order Status Flow
```
pending → confirmed → preparing → ready → dispatched → delivered
                                        ↘ (takeaway/pickup only: skip dispatched)
pending/confirmed/preparing → cancelled
delivered → refunded
```

### Order Types
```
takeaway | pickup | delivery
```

### Order Sources
```
pos | talabat | jahez | hungerstation | other
```

### Shift Status
```
open → closed
(no re-open: create a new shift)
```

### Drawer Session Status
```
open → closed
(one open per cashier enforced by cashier_active_sessions)
```

### Cash Movement Types
```
opening     → in   (initial float)
sale        → in   (order paid)
refund      → out  (order refunded)
cash_in     → in   (manual add)
cash_out    → out  (manual remove / expense)
closing     → out  (drawer closing reconciliation)
```

### Inventory Transaction Types
```
purchase        → stock increase
sale_deduction  → stock decrease (from completed order)
adjustment      → stock correction (counted inventory)
waste           → stock decrease (spoilage)
return          → stock increase (return to supplier)
transfer_in     → stock increase
transfer_out    → stock decrease
```

---

## ═══════════════════════════════════════
## CRITICAL CONSTRAINTS SUMMARY
## ═══════════════════════════════════════

| Rule | Enforcement |
|---|---|
| One active drawer per cashier | `cashier_active_sessions.cashier_id` is PK — INSERT fails on duplicate |
| Order requires open shift | FK + app-layer guard before order creation |
| Order requires open drawer | FK + app-layer guard before order creation |
| Shift can't close with open drawers | App-layer: COUNT open drawer sessions before close |
| Money uses DECIMAL only | Column type definition |
| Snapshots on order items | item_name, unit_price, cost_price, variant_name copied at insert time |
| Inventory log is immutable | Application enforces no UPDATE/DELETE on inventory_transactions |
| No shared cashier accounts | users table + auth layer; each user has own credentials + PIN |

---

## ═══════════════════════════════════════
## TABLE COUNT SUMMARY
## ═══════════════════════════════════════

| # | Table | Group |
|---|---|---|
| 1 | users | Auth |
| 2 | roles | Auth |
| 3 | permissions | Auth |
| 4 | role_permissions | Auth (pivot) |
| 5 | user_roles | Auth (pivot) |
| 6 | pos_devices | Operations |
| 7 | shifts | Operations |
| 8 | cashier_drawer_sessions | Operations |
| 9 | cashier_active_sessions | Operations (guard) |
| 10 | cash_movements | Operations |
| 11 | customers | Orders |
| 12 | orders | Orders |
| 13 | order_payments | Orders |
| 14 | order_items | Orders |
| 15 | order_item_modifiers | Orders |
| 16 | menu_categories | Menu |
| 17 | menu_items | Menu |
| 18 | menu_item_variants | Menu |
| 19 | modifier_groups | Menu |
| 20 | menu_item_modifier_groups | Menu (pivot) |
| 21 | menu_item_modifiers | Menu |
| 22 | suppliers | Inventory |
| 23 | inventory_items | Inventory |
| 24 | inventory_transactions | Inventory |
| 25 | purchases | Inventory |
| 26 | purchase_items | Inventory |
| 27 | expense_categories | Financial |
| 28 | expenses | Financial |

**Total: 28 tables**

---

*Step 1 Complete — Next: Laravel migrations, models, and relationships.*
