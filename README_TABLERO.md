# 🔧 Tablero de Taller - Dashboard de Órdenes de Servicio

## 📋 Descripción

Tablero visual para mostrar en pantalla grande en el taller. Muestra información en tiempo real de las Órdenes de Servicio de la última semana.

## 🚀 Instalación y Configuración

### Requisitos
- XAMPP instalado
- PHP 7.4 o superior
- Extensión `sqlsrv` habilitada en PHP
- SQL Server 2012 o superior
- Navegador moderno (Chrome, Edge, Firefox)

### Archivos del Proyecto
- `tablero.html` - Interfaz visual del tablero (archivo principal)
- `api_tablero.php` - API para obtener datos de la base de datos
- `conexion.php` - Configuración de conexión a SQL Server

### Configuración de la Base de Datos

La conexión a la base de datos ya está configurada en `conexion.php`:

```php
$serverName = "10.48.22.96";
$database = "SITIC_KWSIN2";
$username = "consultadatos";
$password = "QUERYDATA";
```

## 📊 Indicadores Mostrados

### Tarjetas Principales
1. **Total OS Abiertas** - Cantidad total de órdenes de servicio abiertas en la última semana
2. **OS Sin Técnico** - Órdenes que aún no tienen técnico asignado
3. **OS Con Técnico** - Órdenes que ya tienen técnico asignado
4. **Promedio Días Estadía** - Promedio de días que las órdenes permanecen en el taller

### Gráficos
1. **OS por Tipo de Servicio** - Gráfico de barras mostrando la distribución por tipo de servicio
2. **OS por Cliente (Top 10)** - Gráfico circular con los 10 clientes principales
3. **OS por Día** - Gráfico de línea mostrando la evolución diaria de órdenes en la última semana
4. **OS por Estado Operativo** - Gráfico de barras horizontales con los estados operativos

### Tabla de Órdenes Recientes
Muestra las 20 órdenes más recientes con:
- Número de orden
- Unidad
- Tipo de servicio
- Cliente
- Técnico asignado
- Estado operativo
- Días de estadía
- Fecha de la orden

## 🖥️ Uso

### Modo de Operación

1. Abrir el archivo `tablero.html` en un navegador
2. El tablero se actualizará automáticamente cada **10 minutos**
3. Modo completamente visual - **sin interacción del usuario**
4. No incluye botones ni cuadros de búsqueda

### Para Pantalla Grande

1. Abrir en modo pantalla completa (F11 en la mayoría de navegadores)
2. Recomendado: Resolución mínima de 1920x1080
3. El diseño es responsive y se adapta a diferentes tamaños

### URL de Acceso

Si está en XAMPP:
```
http://localhost/tablero-taller/tablero.html
```

O desde la red local:
```
http://[IP_DEL_SERVIDOR]/tablero-taller/tablero.html
```

## 🔄 Actualización Automática

- **Frecuencia**: Cada 10 minutos (600 segundos)
- **Indicador visual**: Contador regresivo en la parte superior
- **Período de datos**: Últimos 7 días
- **Filtro**: Solo órdenes con estado 'S' (Abierta)

## 🎨 Características Visuales

- Diseño moderno con gradientes
- Tarjetas con animaciones al pasar el mouse
- Gráficos interactivos usando Chart.js
- Indicador de conexión en tiempo real
- Tabla con scroll automático
- Colores codificados por estado y urgencia

## 📱 Compatibilidad

- ✅ Chrome 90+
- ✅ Edge 90+
- ✅ Firefox 88+
- ✅ Safari 14+

## 🔧 Solución de Problemas

### El tablero no muestra datos
1. Verificar que XAMPP esté ejecutándose
2. Verificar conexión a SQL Server
3. Revisar archivo `conexion.php`
4. Abrir consola del navegador (F12) para ver errores

### Error de conexión a la base de datos
1. Verificar que el servidor SQL Server esté accesible
2. Comprobar credenciales en `conexion.php`
3. Verificar que la extensión `sqlsrv` esté habilitada en PHP

### Los gráficos no se muestran
1. Verificar conexión a internet (para cargar Chart.js desde CDN)
2. Verificar que JavaScript esté habilitado en el navegador

## 📝 Notas Importantes

- El tablero solo muestra datos de la **última semana**
- Solo muestra órdenes con estado **'S' (Abierta)**
- La actualización es **automática** cada 10 minutos
- No requiere **ninguna interacción** del usuario
- Diseñado para **visualización continua** en pantalla grande

## 🔐 Seguridad

- Usuario de base de datos: `consultadatos` (solo lectura)
- No permite modificaciones a la base de datos
- API solo retorna datos, no acepta POST/PUT/DELETE

## 📞 Soporte

Para modificaciones o mejoras, contactar al administrador del sistema.
