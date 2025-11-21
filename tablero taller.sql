WITH EstadosRankeados AS (
    SELECT 
        NumeroOrden,
        o.IdOrdenServicio,
        p.Nombre AS EstadoOperativo,
        po.FechaHoraInicio,
        ISNULL(ISNULL(po.FechaHoraFin, o.FechaHoraPaseSalida), GETDATE()) AS FechaHoraFin,
        DATEDIFF(HOUR, po.FechaHoraInicio, ISNULL(ISNULL(po.FechaHoraFin, o.FechaHoraPaseSalida), GETDATE())) AS HorasEstadia,
        DATEDIFF(SECOND, po.FechaHoraInicio, ISNULL(ISNULL(po.FechaHoraFin, o.FechaHoraPaseSalida), GETDATE())) / 86400.0 AS DiasEstadia,
        po.Comentarios AS ComentariosEstadía,
        ROW_NUMBER() OVER (PARTITION BY NumeroOrden ORDER BY po.FechaHoraInicio DESC) AS Rn
    FROM
        tdsOrdenesServicio o (nolock)
        INNER JOIN tdsEstadosOperativosOrdenes po (nolock) ON po.IdOrdenServicio = o.IdOrdenServicio
        INNER JOIN tdsEstadosOperativos p (nolock) ON p.IdEstadoOperativo = po.IdEstadoOperativo
    WHERE
        o.FechaHoraOrden >= '2025-15-10'
        AND o.IdSucursal = 1
        AND o.estadoOrden NOT IN ('N','F')
),
BahiasRankeadas AS (
    SELECT 
        bu.IdArticulo,
        b.Descripcion AS Bahía,
        ROW_NUMBER() OVER (PARTITION BY bu.IdArticulo ORDER BY bu.IdUbicacionT4) AS RnBahia
    FROM 
        invArticulosUbicacionesT4 bu (nolock)
        INNER JOIN invUbicacionesT4 b (nolock) ON bu.IdUbicacionT4 = b.IdUbicacionT4
),
TecnicosAsignados AS (
    SELECT 
        o.IdOrdenServicio,
        tt.IdTecnico,
        m.Nombre AS NombreTecnico,
        ROW_NUMBER() OVER (PARTITION BY o.IdOrdenServicio ORDER BY t.FechaInicio DESC) AS RnTecnico
    FROM 
        tdsTrabajosTecnicos tt (nolock)
        INNER JOIN tdsTecnicos m (nolock) ON tt.IdTecnico = m.IdTecnico
        INNER JOIN tdsTrabajos t (nolock) ON t.IdTrabajo = tt.IdTrabajo
        INNER JOIN tdsOrdenesServicio o (nolock) ON t.IdOrdenServicio = o.IdOrdenServicio
    WHERE 
        o.IdSucursal = 1
)
SELECT  
    o.NumeroOrden,
    a.NumArticulo AS Unidad,
    t.Nombre AS TipoServicio,
    s.NombreSucursal,
    c.NombreCalculado,
    o.FechaHoraOrden,
    o.FechaHoraPromEntrega,
    CASE o.estadoOrden
       -- WHEN 'C' THEN 'Cerrada'
        WHEN 'S' THEN 'Abierta'
       -- WHEN 'P' THEN 'Parcialmente Facturada Cerrada'
       -- WHEN 'A' THEN 'Parcialmente Facturada Abierta'
        ELSE 'NINGUNO'
    END AS EstadoOS,
    o.FechaVencimientoOS,
    o.Comentarios,
    o.OrdenReparacion,
    o.FechaHoraCierre,
    o.FechaHorapaseSalida,
    u.NombreUsuario,
    br.Bahía,
    er.EstadoOperativo,
    er.FechaHoraInicio AS EstadoFechaInicio,
    er.FechaHoraFin AS EstadoFechaFin,
    er.HorasEstadia,
    er.DiasEstadia,
    er.ComentariosEstadía,
    ta.IdTecnico,
    ta.NombreTecnico
FROM  
    tdsOrdenesServicio o (nolock)
    INNER JOIN tdsTiposervicio t (nolock) ON o.IdtipoServicio = t.IdtipoServicio
    INNER JOIN admSucursales s (nolock) ON s.iDsucursal = o.iDsucursal
    INNER JOIN cpcClientes c (nolock) ON c.IdCliente = o.IdCliente
    INNER JOIN admUsuarios u (nolock) ON u.IdUsuario = o.IdUsuario
    INNER JOIN invArticulos a (nolock) ON o.IdArticulo = a.IdArticulo
    LEFT JOIN BahiasRankeadas br ON o.IdArticulo = br.IdArticulo AND br.RnBahia = 1
    LEFT JOIN EstadosRankeados er ON o.NumeroOrden = er.NumeroOrden AND er.Rn = 1
    LEFT JOIN TecnicosAsignados ta ON o.IdOrdenServicio = ta.IdOrdenServicio AND ta.RnTecnico = 1
WHERE 
    o.estadoOrden = 'S'
    AND o.FechaHoraOrden >= '2025-15-10'
    AND o.estadoOrden NOT IN ('F','N')
    AND o.IdSucursal = 1
    
ORDER BY o.NumeroOrden DESC