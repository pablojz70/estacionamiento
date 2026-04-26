# Estacionamiento - Parking Lot Management System

## Stack
- PHP + MySQL (XAMPP)
- Database: `parking_db`
- Files: `/opt/lampp/htdocs/Proyecto/`

## Key Files
| File | Purpose |
|------|---------|
| `db.php` | Database connection, all functions |
| `index.php` | Vehicle entry registration (empleado) |
| `salida.php` | Vehicle exit + payment |
| `cierre.php` | Daily reports |
| `admin.php` | Tariffs, fixed vehicles, users config |
| `login.php` | Authentication |
| `style.css` | All UI styling |

## Database Connection (db.php)
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');  // Usually empty
define('DB_NAME', 'parking_db');
```

## Common Issues

### HTTP 500: bind_param count mismatch
The `vehiculos` INSERT uses 8 columns. Format string must match:
```php
$stmt->bind_param("ssiiissd", $placa, $tipo_vehiculo, $es_fijo, $usuario_id, $id_fijo, $fecha_entrada, $status, $tasa_dolar);
```
If adding columns, update the format string AND pass the value.

### Missing columns in vehiculos table
Run if 500 errors on INSERT:
```sql
ALTER TABLE vehiculos ADD COLUMN tipo_vehiculo VARCHAR(20);
ALTER TABLE vehiculos ADD COLUMN es_fijo TINYINT(1) DEFAULT 0;
ALTER TABLE vehiculos ADD COLUMN id_usuario INT;
ALTER TABLE vehiculos ADD COLUMN id_vehiculo_fijo INT DEFAULT 0;
ALTER TABLE vehiculos ADD COLUMN tasa_dolar DECIMAL(10,2);
ALTER TABLE vehiculos MODIFY COLUMN status ENUM('dentro','pagado','cancelado','fijo_salida');
ALTER TABLE tasas_dolar ADD COLUMN fuente VARCHAR(50);
```

### Print ticket JavaScript - must use setTimeout
Dynamic ticket divs need delay before setting textContent:
```javascript
function printTicketFor(placa, tipo, fecha, esFijo, id) {
    var printDiv = document.getElementById('ticketPrint');
    if (!printDiv) {
        printDiv = document.createElement('div');
        printDiv.id = 'ticketPrint';
        printDiv.className = 'ticket';
        printDiv.innerHTML = '<div class="placa" id="tf_placa"></div>' +
            '<span id="tf_tipo"></span>...';
        document.body.appendChild(printDiv);
    }
    setTimeout(function() {
        var el = document.getElementById('tf_placa');
        if (el) el.textContent = placa;
        printDiv.style.display = 'block';
        window.print();
    }, 100);
}
```

### PHP error log
`/opt/lampp/logs/php_error_log`

## User Types
- `empleado` - register entry/exit
- `dueno` - reports + fixed vehicle payments
- `administrador` - full access

## BCV Dollar Rate
API: `https://ve.dolarapi.com/v1/dolares/oficial` (~481 BS)