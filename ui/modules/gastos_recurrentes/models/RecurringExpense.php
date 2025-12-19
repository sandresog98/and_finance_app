<?php
/**
 * Modelo de Gasto Recurrente
 */

namespace UI\Modules\GastosRecurrentes\Models;

use PDO;
use PDOException;

class RecurringExpense {
    private PDO $conn;
    
    public function __construct(PDO $connection) {
        $this->conn = $connection;
    }
    
    /**
     * Obtener todos los gastos recurrentes de un usuario
     */
    public function getAllByUser(int $userId): array {
        try {
            $stmt = $this->conn->prepare("
                SELECT g.id, g.nombre, g.monto, g.dia_mes, g.tipo, g.estado_activo, g.fecha_creacion,
                       c.nombre AS cuenta_nombre,
                       cat.nombre AS categoria_nombre, cat.icono AS categoria_icono, cat.color AS categoria_color
                FROM gastos_recurrentes_gastos g
                INNER JOIN cuentas_cuentas c ON g.cuenta_id = c.id
                INNER JOIN categorias_categorias cat ON g.categoria_id = cat.id
                WHERE g.usuario_id = ? AND g.estado_activo = TRUE
                ORDER BY g.dia_mes ASC, g.nombre ASC
            ");
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('RecurringExpense::getAllByUser error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtener gasto recurrente por ID
     */
    public function getById(int $id, int $userId): ?array {
        try {
            $stmt = $this->conn->prepare("
                SELECT g.*, c.nombre AS cuenta_nombre, cat.nombre AS categoria_nombre
                FROM gastos_recurrentes_gastos g
                INNER JOIN cuentas_cuentas c ON g.cuenta_id = c.id
                INNER JOIN categorias_categorias cat ON g.categoria_id = cat.id
                WHERE g.id = ? AND g.usuario_id = ? AND g.estado_activo = TRUE
                LIMIT 1
            ");
            $stmt->execute([$id, $userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (PDOException $e) {
            error_log('RecurringExpense::getById error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Crear nuevo gasto recurrente
     */
    public function create(array $data): array {
        try {
            // Validar cuenta
            $stmt = $this->conn->prepare("SELECT id FROM cuentas_cuentas WHERE id = ? AND usuario_id = ? AND estado_activo = TRUE LIMIT 1");
            $stmt->execute([$data['cuenta_id'], $data['usuario_id']]);
            if (!$stmt->fetch()) {
                return ['success' => false, 'message' => 'Cuenta no válida'];
            }
            
            // Validar categoría
            $stmt = $this->conn->prepare("SELECT id FROM categorias_categorias WHERE id = ? AND estado_activo = TRUE LIMIT 1");
            $stmt->execute([$data['categoria_id']]);
            if (!$stmt->fetch()) {
                return ['success' => false, 'message' => 'Categoría no válida'];
            }
            
            // Validar día del mes (1-31)
            $diaMes = (int)$data['dia_mes'];
            if ($diaMes < 1 || $diaMes > 31) {
                return ['success' => false, 'message' => 'El día del mes debe estar entre 1 y 31'];
            }
            
            $stmt = $this->conn->prepare("
                INSERT INTO gastos_recurrentes_gastos 
                (usuario_id, cuenta_id, categoria_id, nombre, monto, dia_mes, tipo, estado_activo)
                VALUES (?, ?, ?, ?, ?, ?, ?, TRUE)
            ");
            
            $stmt->execute([
                $data['usuario_id'],
                $data['cuenta_id'],
                $data['categoria_id'],
                $data['nombre'],
                (float)$data['monto'],
                $diaMes,
                $data['tipo'] ?? 'mensual'
            ]);
            
            $id = $this->conn->lastInsertId();
            return ['success' => true, 'id' => $id];
            
        } catch (PDOException $e) {
            error_log('RecurringExpense::create error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Error al crear el gasto recurrente'];
        }
    }
    
    /**
     * Actualizar gasto recurrente
     */
    public function update(int $id, int $userId, array $data): array {
        try {
            $gasto = $this->getById($id, $userId);
            if (!$gasto) {
                return ['success' => false, 'message' => 'Gasto recurrente no encontrado'];
            }
            
            $fields = [];
            $params = [];
            
            if (isset($data['nombre'])) {
                $fields[] = "nombre = ?";
                $params[] = $data['nombre'];
            }
            if (isset($data['monto'])) {
                $fields[] = "monto = ?";
                $params[] = (float)$data['monto'];
            }
            if (isset($data['dia_mes'])) {
                $diaMes = (int)$data['dia_mes'];
                if ($diaMes < 1 || $diaMes > 31) {
                    return ['success' => false, 'message' => 'El día del mes debe estar entre 1 y 31'];
                }
                $fields[] = "dia_mes = ?";
                $params[] = $diaMes;
            }
            if (isset($data['tipo'])) {
                $fields[] = "tipo = ?";
                $params[] = $data['tipo'];
            }
            if (isset($data['cuenta_id'])) {
                // Validar cuenta
                $stmt = $this->conn->prepare("SELECT id FROM cuentas_cuentas WHERE id = ? AND usuario_id = ? AND estado_activo = TRUE LIMIT 1");
                $stmt->execute([$data['cuenta_id'], $userId]);
                if (!$stmt->fetch()) {
                    return ['success' => false, 'message' => 'Cuenta no válida'];
                }
                $fields[] = "cuenta_id = ?";
                $params[] = $data['cuenta_id'];
            }
            if (isset($data['categoria_id'])) {
                // Validar categoría
                $stmt = $this->conn->prepare("SELECT id FROM categorias_categorias WHERE id = ? AND estado_activo = TRUE LIMIT 1");
                $stmt->execute([$data['categoria_id']]);
                if (!$stmt->fetch()) {
                    return ['success' => false, 'message' => 'Categoría no válida'];
                }
                $fields[] = "categoria_id = ?";
                $params[] = $data['categoria_id'];
            }
            
            if (empty($fields)) {
                return ['success' => false, 'message' => 'No hay campos para actualizar'];
            }
            
            $params[] = $id;
            $params[] = $userId;
            
            $sql = "UPDATE gastos_recurrentes_gastos SET " . implode(", ", $fields) . " WHERE id = ? AND usuario_id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            
            return ['success' => true];
            
        } catch (PDOException $e) {
            error_log('RecurringExpense::update error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Error al actualizar el gasto recurrente'];
        }
    }
    
    /**
     * Eliminar gasto recurrente
     */
    public function delete(int $id, int $userId): array {
        try {
            $gasto = $this->getById($id, $userId);
            if (!$gasto) {
                return ['success' => false, 'message' => 'Gasto recurrente no encontrado'];
            }
            
            $stmt = $this->conn->prepare("
                UPDATE gastos_recurrentes_gastos 
                SET estado_activo = FALSE 
                WHERE id = ? AND usuario_id = ?
            ");
            $stmt->execute([$id, $userId]);
            
            return ['success' => true];
            
        } catch (PDOException $e) {
            error_log('RecurringExpense::delete error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Error al eliminar el gasto recurrente'];
        }
    }
    
    /**
     * Obtener proyección de gastos para un mes y año específicos
     * Incluye gastos futuros y pasados que no han sido ignorados ni ejecutados
     */
    public function getProjection(int $userId, int $mes, int $anio): array {
        try {
            $fechaActual = new \DateTime();
            $mesActual = (int)$fechaActual->format('n');
            $anioActual = (int)$fechaActual->format('Y');
            $diaActual = (int)$fechaActual->format('j');
            
            // Verificar si el campo ignorado existe
            $stmt = $this->conn->query("SHOW COLUMNS FROM gastos_recurrentes_ejecuciones LIKE 'ignorado'");
            $tieneIgnorado = $stmt->rowCount() > 0;
            
            if ($tieneIgnorado) {
                $sql = "
                    SELECT g.id, g.nombre, g.monto, g.dia_mes, g.tipo, g.cuenta_id, g.categoria_id, g.fecha_creacion,
                           c.nombre AS cuenta_nombre,
                           cat.nombre AS categoria_nombre, cat.icono AS categoria_icono, cat.color AS categoria_color,
                           e.ejecutado, COALESCE(e.ignorado, FALSE) AS ignorado, e.transaccion_id,
                           CASE 
                               WHEN g.dia_mes < ? AND ? = ? AND ? = ? THEN TRUE 
                               ELSE FALSE 
                           END AS es_pasado
                    FROM gastos_recurrentes_gastos g
                    INNER JOIN cuentas_cuentas c ON g.cuenta_id = c.id
                    INNER JOIN categorias_categorias cat ON g.categoria_id = cat.id
                    LEFT JOIN gastos_recurrentes_ejecuciones e ON g.id = e.gasto_recurrente_id AND e.mes = ? AND e.anio = ?
                    WHERE g.usuario_id = ? AND g.estado_activo = TRUE
                    AND (e.ignorado IS NULL OR e.ignorado = FALSE)
                ";
            } else {
                $sql = "
                    SELECT g.id, g.nombre, g.monto, g.dia_mes, g.tipo, g.cuenta_id, g.categoria_id, g.fecha_creacion,
                           c.nombre AS cuenta_nombre,
                           cat.nombre AS categoria_nombre, cat.icono AS categoria_icono, cat.color AS categoria_color,
                           e.ejecutado, FALSE AS ignorado, e.transaccion_id,
                           CASE 
                               WHEN g.dia_mes < ? AND ? = ? AND ? = ? THEN TRUE 
                               ELSE FALSE 
                           END AS es_pasado
                    FROM gastos_recurrentes_gastos g
                    INNER JOIN cuentas_cuentas c ON g.cuenta_id = c.id
                    INNER JOIN categorias_categorias cat ON g.categoria_id = cat.id
                    LEFT JOIN gastos_recurrentes_ejecuciones e ON g.id = e.gasto_recurrente_id AND e.mes = ? AND e.anio = ?
                    WHERE g.usuario_id = ? AND g.estado_activo = TRUE
                ";
            }
            
            $params = [
                $diaActual, // Para comparar con dia_mes
                $mes, $mesActual, // Para comparar mes
                $anio, $anioActual, // Para comparar año
                $mes, $anio, // Para el LEFT JOIN
                $userId
            ];
            
            $sql .= " ORDER BY g.dia_mes ASC";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Filtrar resultados según la frecuencia del gasto
            $resultadosFiltrados = [];
            foreach ($resultados as $gasto) {
                if ($this->shouldExecuteInMonth($gasto, $mes, $anio)) {
                    $resultadosFiltrados[] = $gasto;
                }
            }
            
            return $resultadosFiltrados;
        } catch (PDOException $e) {
            error_log('RecurringExpense::getProjection error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Verificar si un gasto debe ejecutarse en un mes/año específico según su frecuencia
     */
    public function shouldExecuteInMonth(array $gasto, int $mes, int $anio): bool {
        $tipo = $gasto['tipo'];
        $diaMes = (int)$gasto['dia_mes'];
        
        // Calcular días en el mes
        $diasEnMes = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);
        if ($diaMes > $diasEnMes) {
            return false; // El día no existe en ese mes
        }
        
        // Obtener mes de creación del gasto
        $fechaCreacion = new \DateTime($gasto['fecha_creacion']);
        $mesCreacion = (int)$fechaCreacion->format('n');
        $anioCreacion = (int)$fechaCreacion->format('Y');
        
        switch ($tipo) {
            case 'mensual':
                return true; // Se ejecuta todos los meses
                
            case 'quincenal':
                // Se ejecuta el día 15 y el último día del mes
                return ($diaMes == 15 || $diaMes == $diasEnMes);
                
            case 'semanal':
                // Para semanal, necesitaríamos calcular las semanas del mes
                // Por simplicidad, asumimos que se ejecuta cada 7 días desde el día del mes
                $fechaGasto = new \DateTime("{$anio}-{$mes}-{$diaMes}");
                $primerDiaMes = new \DateTime("{$anio}-{$mes}-01");
                $diferencia = $fechaGasto->diff($primerDiaMes)->days;
                return ($diferencia % 7 == 0);
                
            case 'bimestral':
                // Se ejecuta cada 2 meses desde el mes de creación
                $diferenciaMeses = (($anio - $anioCreacion) * 12) + ($mes - $mesCreacion);
                return ($diferenciaMeses >= 0 && $diferenciaMeses % 2 == 0);
                
            case 'trimestral':
                // Se ejecuta cada 3 meses desde el mes de creación
                $diferenciaMeses = (($anio - $anioCreacion) * 12) + ($mes - $mesCreacion);
                return ($diferenciaMeses >= 0 && $diferenciaMeses % 3 == 0);
                
            case 'semestral':
                // Se ejecuta cada 6 meses desde el mes de creación
                $diferenciaMeses = (($anio - $anioCreacion) * 12) + ($mes - $mesCreacion);
                return ($diferenciaMeses >= 0 && $diferenciaMeses % 6 == 0);
                
            case 'anual':
                // Se ejecuta una vez al año en el mismo mes de creación
                return ($mes == $mesCreacion && $anio >= $anioCreacion);
                
            default:
                return true;
        }
    }
    
    /**
     * Verificar si un gasto debe ejecutarse en un mes/año según su tipo
     * @deprecated Usar shouldExecuteInMonth en su lugar
     */
    public function shouldExecute(array $gasto, int $mes, int $anio): bool {
        return $this->shouldExecuteInMonth($gasto, $mes, $anio);
    }
    
    /**
     * Ejecutar un gasto recurrente (crear transacción)
     */
    public function execute(int $gastoId, int $userId, int $mes, int $anio): array {
        try {
            $gasto = $this->getById($gastoId, $userId);
            if (!$gasto) {
                return ['success' => false, 'message' => 'Gasto recurrente no encontrado'];
            }
            
            // Verificar si ya fue ejecutado o ignorado
            // Verificar si el campo ignorado existe
            $stmt = $this->conn->query("SHOW COLUMNS FROM gastos_recurrentes_ejecuciones LIKE 'ignorado'");
            $tieneIgnorado = $stmt->rowCount() > 0;
            
            if ($tieneIgnorado) {
                $stmt = $this->conn->prepare("
                    SELECT id, ejecutado, ignorado FROM gastos_recurrentes_ejecuciones 
                    WHERE gasto_recurrente_id = ? AND mes = ? AND anio = ?
                ");
            } else {
                $stmt = $this->conn->prepare("
                    SELECT id, ejecutado FROM gastos_recurrentes_ejecuciones 
                    WHERE gasto_recurrente_id = ? AND mes = ? AND anio = ?
                ");
            }
            $stmt->execute([$gastoId, $mes, $anio]);
            $ejecucion = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($ejecucion) {
                if ($ejecucion['ejecutado']) {
                    return ['success' => false, 'message' => 'Este gasto ya fue ejecutado para este mes'];
                }
                if ($tieneIgnorado && !empty($ejecucion['ignorado'])) {
                    return ['success' => false, 'message' => 'Este gasto fue ignorado para este mes'];
                }
            }
            
            // Calcular fecha de ejecución
            $diasEnMes = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);
            $diaEjecucion = min($gasto['dia_mes'], $diasEnMes);
            $fechaEjecucion = sprintf('%04d-%02d-%02d', $anio, $mes, $diaEjecucion);
            
            // Crear transacción
            $this->conn->beginTransaction();
            
            // Verificar si el campo es_programada existe en la tabla
            $stmt = $this->conn->query("SHOW COLUMNS FROM transacciones_transacciones LIKE 'es_programada'");
            $tieneEsProgramada = $stmt->rowCount() > 0;
            
            if ($tieneEsProgramada) {
                $stmt = $this->conn->prepare("
                    INSERT INTO transacciones_transacciones 
                    (usuario_id, cuenta_id, categoria_id, tipo, monto, fecha, comentario, es_programada, estado_activo)
                    VALUES (?, ?, ?, 'egreso', ?, ?, ?, FALSE, TRUE)
                ");
            } else {
                $stmt = $this->conn->prepare("
                    INSERT INTO transacciones_transacciones 
                    (usuario_id, cuenta_id, categoria_id, tipo, monto, fecha, comentario, estado_activo)
                    VALUES (?, ?, ?, 'egreso', ?, ?, ?, TRUE)
                ");
            }
            
            $comentario = "Gasto recurrente: " . $gasto['nombre'];
            
            if ($tieneEsProgramada) {
                $stmt->execute([
                    $userId,
                    $gasto['cuenta_id'],
                    $gasto['categoria_id'],
                    (float)$gasto['monto'],
                    $fechaEjecucion,
                    $comentario
                ]);
            } else {
                $stmt->execute([
                    $userId,
                    $gasto['cuenta_id'],
                    $gasto['categoria_id'],
                    (float)$gasto['monto'],
                    $fechaEjecucion,
                    $comentario
                ]);
            }
            
            $transaccionId = $this->conn->lastInsertId();
            
            if (!$transaccionId) {
                throw new \Exception('No se pudo obtener el ID de la transacción creada');
            }
            
            // Actualizar saldo de cuenta
            $stmt = $this->conn->prepare("SELECT saldo_actual FROM cuentas_cuentas WHERE id = ?");
            $stmt->execute([$gasto['cuenta_id']]);
            $cuenta = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$cuenta) {
                throw new \Exception('No se encontró la cuenta asociada al gasto');
            }
            
            $nuevoSaldo = $cuenta['saldo_actual'] - (float)$gasto['monto'];
            
            $stmt = $this->conn->prepare("UPDATE cuentas_cuentas SET saldo_actual = ? WHERE id = ?");
            $stmt->execute([$nuevoSaldo, $gasto['cuenta_id']]);
            
            // Registrar ejecución
            // Verificar si el campo ignorado existe
            $stmt = $this->conn->query("SHOW COLUMNS FROM gastos_recurrentes_ejecuciones LIKE 'ignorado'");
            $tieneIgnorado = $stmt->rowCount() > 0;
            
            if ($tieneIgnorado) {
                $stmt = $this->conn->prepare("
                    INSERT INTO gastos_recurrentes_ejecuciones 
                    (gasto_recurrente_id, transaccion_id, mes, anio, ejecutado, ignorado, fecha_ejecucion)
                    VALUES (?, ?, ?, ?, TRUE, FALSE, NOW())
                ");
            } else {
                $stmt = $this->conn->prepare("
                    INSERT INTO gastos_recurrentes_ejecuciones 
                    (gasto_recurrente_id, transaccion_id, mes, anio, ejecutado, fecha_ejecucion)
                    VALUES (?, ?, ?, ?, TRUE, NOW())
                ");
            }
            $stmt->execute([$gastoId, $transaccionId, $mes, $anio]);
            
            $this->conn->commit();
            return ['success' => true, 'transaccion_id' => $transaccionId];
            
        } catch (\PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log('RecurringExpense::execute PDO error: ' . $e->getMessage());
            error_log('SQL State: ' . $e->getCode());
            return ['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()];
        } catch (\Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log('RecurringExpense::execute error: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            return ['success' => false, 'message' => 'Error al ejecutar el gasto recurrente: ' . $e->getMessage()];
        }
    }
    
    /**
     * Calcular saldo actual total del usuario (suma de todas las cuentas)
     */
    public function getCurrentBalance(int $userId): float {
        try {
            $stmt = $this->conn->prepare("
                SELECT COALESCE(SUM(saldo_actual), 0) AS total
                FROM cuentas_cuentas
                WHERE usuario_id = ? AND estado_activo = TRUE
            ");
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (float)($result['total'] ?? 0);
        } catch (PDOException $e) {
            error_log('RecurringExpense::getCurrentBalance error: ' . $e->getMessage());
            return 0.0;
        }
    }
    
    /**
     * Calcular saldo proyectado a fin de mes considerando:
     * - Saldo actual
     * - Transacciones programadas hasta fin de mes
     * - Gastos recurrentes pendientes hasta fin de mes
     */
    public function getProjectedBalanceEndOfMonth(int $userId, int $mes, int $anio): float {
        try {
            $saldoActual = $this->getCurrentBalance($userId);
            
            // Calcular días en el mes
            $diasEnMes = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);
            $fechaInicioMes = sprintf('%04d-%02d-%02d', $anio, $mes, 1);
            $fechaFinMes = sprintf('%04d-%02d-%02d', $anio, $mes, $diasEnMes);
            
            // Obtener fecha actual
            $fechaActual = new \DateTime();
            $fechaActualStr = $fechaActual->format('Y-m-d');
            
            // Sumar transacciones programadas desde hoy hasta fin de mes
            // Nota: Las transferencias no afectan el saldo total, solo mueven dinero entre cuentas
            $stmt = $this->conn->prepare("
                SELECT 
                    SUM(CASE WHEN tipo = 'ingreso' THEN monto ELSE 0 END) AS total_ingresos,
                    SUM(CASE WHEN tipo = 'egreso' THEN monto ELSE 0 END) AS total_egresos
                FROM transacciones_transacciones
                WHERE usuario_id = ? 
                AND es_programada = TRUE 
                AND estado_activo = TRUE
                AND tipo != 'transferencia'
                AND fecha >= ?
                AND fecha <= ?
            ");
            $stmt->execute([$userId, $fechaActualStr, $fechaFinMes]);
            $transacciones = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $ingresosProgramados = (float)($transacciones['total_ingresos'] ?? 0);
            $egresosProgramados = (float)($transacciones['total_egresos'] ?? 0);
            
            // Sumar gastos recurrentes pendientes hasta fin de mes (excluyendo ignorados)
            $proyeccion = $this->getProjection($userId, $mes, $anio);
            $totalGastosRecurrentes = 0;
            foreach ($proyeccion as $gasto) {
                if (empty($gasto['ejecutado']) && empty($gasto['ignorado'])) {
                    $totalGastosRecurrentes += (float)$gasto['monto'];
                }
            }
            
            // Calcular saldo proyectado
            $saldoProyectado = $saldoActual + $ingresosProgramados - $egresosProgramados - $totalGastosRecurrentes;
            
            return $saldoProyectado;
        } catch (PDOException $e) {
            error_log('RecurringExpense::getProjectedBalanceEndOfMonth error: ' . $e->getMessage());
            return $saldoActual;
        }
    }
    
    /**
     * Ignorar un gasto recurrente para un mes/año específico
     */
    public function ignore(int $gastoId, int $userId, int $mes, int $anio): array {
        try {
            // Verificar si el campo ignorado existe
            $stmt = $this->conn->query("SHOW COLUMNS FROM gastos_recurrentes_ejecuciones LIKE 'ignorado'");
            $tieneIgnorado = $stmt->rowCount() > 0;
            
            if (!$tieneIgnorado) {
                return ['success' => false, 'message' => 'La funcionalidad de ignorar no está disponible. Por favor ejecute el script de migración: sql/add_ignorado_column.sql'];
            }
            
            $gasto = $this->getById($gastoId, $userId);
            if (!$gasto) {
                return ['success' => false, 'message' => 'Gasto recurrente no encontrado'];
            }
            
            // Verificar si ya fue ejecutado o ignorado
            $stmt = $this->conn->prepare("
                SELECT id, ejecutado, ignorado FROM gastos_recurrentes_ejecuciones 
                WHERE gasto_recurrente_id = ? AND mes = ? AND anio = ?
            ");
            $stmt->execute([$gastoId, $mes, $anio]);
            $ejecucion = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($ejecucion) {
                if ($ejecucion['ejecutado']) {
                    return ['success' => false, 'message' => 'Este gasto ya fue ejecutado para este mes'];
                }
                if ($ejecucion['ignorado']) {
                    return ['success' => false, 'message' => 'Este gasto ya fue ignorado para este mes'];
                }
                // Actualizar registro existente
                $stmt = $this->conn->prepare("
                    UPDATE gastos_recurrentes_ejecuciones 
                    SET ignorado = TRUE 
                    WHERE id = ?
                ");
                $stmt->execute([$ejecucion['id']]);
            } else {
                // Crear nuevo registro de ignorado
                $stmt = $this->conn->prepare("
                    INSERT INTO gastos_recurrentes_ejecuciones 
                    (gasto_recurrente_id, mes, anio, ejecutado, ignorado)
                    VALUES (?, ?, ?, FALSE, TRUE)
                ");
                $stmt->execute([$gastoId, $mes, $anio]);
            }
            
            return ['success' => true];
            
        } catch (\Exception $e) {
            error_log('RecurringExpense::ignore error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Error al ignorar el gasto recurrente: ' . $e->getMessage()];
        }
    }
}
