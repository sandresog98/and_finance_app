<?php
/**
 * Modelo de Transacción
 */

namespace UI\Modules\Transacciones\Models;

use PDO;
use PDOException;

class Transaction {
    private PDO $conn;
    
    public function __construct(PDO $connection) {
        $this->conn = $connection;
    }
    
    /**
     * Obtener todas las transacciones de un usuario con filtros
     */
    public function getAllByUser(int $userId, array $filters = []): array {
        try {
            $sql = "
                SELECT t.id, t.tipo, t.monto, t.fecha, t.comentario, t.estado_activo, t.fecha_creacion,
                       c.nombre AS cuenta_nombre, c.tipo AS cuenta_tipo,
                       cat.nombre AS categoria_nombre, cat.tipo AS categoria_tipo, cat.icono AS categoria_icono, cat.color AS categoria_color,
                       cd.nombre AS cuenta_destino_nombre
                FROM transacciones_transacciones t
                INNER JOIN cuentas_cuentas c ON t.cuenta_id = c.id
                LEFT JOIN categorias_categorias cat ON t.categoria_id = cat.id
                LEFT JOIN cuentas_cuentas cd ON t.cuenta_destino_id = cd.id
                WHERE t.usuario_id = ? AND t.estado_activo = TRUE
            ";
            
            $params = [$userId];
            
            // Aplicar filtros
            if (!empty($filters['fecha_desde'])) {
                $sql .= " AND t.fecha >= ?";
                $params[] = $filters['fecha_desde'];
            }
            if (!empty($filters['fecha_hasta'])) {
                $sql .= " AND t.fecha <= ?";
                $params[] = $filters['fecha_hasta'];
            }
            if (!empty($filters['tipo'])) {
                $sql .= " AND t.tipo = ?";
                $params[] = $filters['tipo'];
            }
            if (!empty($filters['categoria_id'])) {
                $sql .= " AND t.categoria_id = ?";
                $params[] = $filters['categoria_id'];
            }
            if (!empty($filters['cuenta_id'])) {
                $sql .= " AND t.cuenta_id = ?";
                $params[] = $filters['cuenta_id'];
            }
            
            $sql .= " ORDER BY t.fecha DESC, t.fecha_creacion DESC LIMIT 500";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Transaction::getAllByUser error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtener últimas transacciones de una cuenta específica
     */
    public function getLastByAccount(int $accountId, int $userId, int $limit = 10): array {
        try {
            $sql = "
                SELECT t.id, t.tipo, t.monto, t.fecha, t.comentario, t.estado_activo, t.fecha_creacion,
                       c.nombre AS cuenta_nombre, c.tipo AS cuenta_tipo,
                       cat.nombre AS categoria_nombre, cat.tipo AS categoria_tipo, cat.icono AS categoria_icono, cat.color AS categoria_color,
                       cd.nombre AS cuenta_destino_nombre
                FROM transacciones_transacciones t
                INNER JOIN cuentas_cuentas c ON t.cuenta_id = c.id
                LEFT JOIN categorias_categorias cat ON t.categoria_id = cat.id
                LEFT JOIN cuentas_cuentas cd ON t.cuenta_destino_id = cd.id
                WHERE t.usuario_id = ? AND t.estado_activo = TRUE
                AND (t.cuenta_id = ? OR t.cuenta_destino_id = ?)
                ORDER BY t.fecha DESC, t.fecha_creacion DESC
                LIMIT ?
            ";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$userId, $accountId, $accountId, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Transaction::getLastByAccount error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtener transacción por ID
     */
    public function getById(int $id, int $userId): ?array {
        try {
            // Verificar si la columna es_programada existe
            $stmt = $this->conn->query("SHOW COLUMNS FROM transacciones_transacciones LIKE 'es_programada'");
            $tieneEsProgramada = $stmt->rowCount() > 0;
            
            $sql = "
                SELECT t.*, c.nombre AS cuenta_nombre, cat.nombre AS categoria_nombre";
            if ($tieneEsProgramada) {
                $sql .= ", t.es_programada";
            }
            $sql .= "
                FROM transacciones_transacciones t
                INNER JOIN cuentas_cuentas c ON t.cuenta_id = c.id
                LEFT JOIN categorias_categorias cat ON t.categoria_id = cat.id
                WHERE t.id = ? AND t.usuario_id = ? AND t.estado_activo = TRUE
                LIMIT 1
            ";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$id, $userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Si no existe la columna, agregar un valor por defecto
            if ($result && !$tieneEsProgramada) {
                $result['es_programada'] = false;
            }
            
            return $result ?: null;
        } catch (PDOException $e) {
            error_log('Transaction::getById error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Crear nueva transacción
     */
    public function create(array $data): array {
        try {
            $this->conn->beginTransaction();
            
            // Validar cuenta
            $stmt = $this->conn->prepare("SELECT id, saldo_actual FROM cuentas_cuentas WHERE id = ? AND usuario_id = ? AND estado_activo = TRUE LIMIT 1");
            $stmt->execute([$data['cuenta_id'], $data['usuario_id']]);
            $cuenta = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$cuenta) {
                throw new \Exception('Cuenta no válida');
            }
            
            $monto = (float)$data['monto'];
            $tipo = $data['tipo'];
            
            // Validar categoría (solo para ingresos y egresos, no para transferencias ni ajustes)
            $categoriaId = null;
            if ($tipo !== 'transferencia' && $tipo !== 'ajuste' && !empty($data['categoria_id'])) {
                $stmt = $this->conn->prepare("SELECT id, tipo FROM categorias_categorias WHERE id = ? AND estado_activo = TRUE LIMIT 1");
                $stmt->execute([$data['categoria_id']]);
                $categoria = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$categoria) {
                    throw new \Exception('Categoría no válida');
                }
                $categoriaId = $data['categoria_id'];
            }
            
            // Para transferencias, validar cuenta destino
            if ($tipo === 'transferencia') {
                if (empty($data['cuenta_destino_id'])) {
                    throw new \Exception('Cuenta destino requerida para transferencias');
                }
                
                $stmt = $this->conn->prepare("SELECT id, saldo_actual FROM cuentas_cuentas WHERE id = ? AND usuario_id = ? AND estado_activo = TRUE LIMIT 1");
                $stmt->execute([$data['cuenta_destino_id'], $data['usuario_id']]);
                $cuentaDestino = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$cuentaDestino) {
                    throw new \Exception('Cuenta destino no válida');
                }
                
                if ($data['cuenta_id'] == $data['cuenta_destino_id']) {
                    throw new \Exception('No se puede transferir a la misma cuenta');
                }
            }
            
            // Determinar si es programada (fecha futura)
            $esProgramada = isset($data['es_programada']) ? (bool)$data['es_programada'] : false;
            $fechaTransaccion = new \DateTime($data['fecha']);
            $fechaActual = new \DateTime();
            
            // Si la fecha es futura, es programada automáticamente
            if ($fechaTransaccion > $fechaActual) {
                $esProgramada = true;
            }
            
            // Insertar transacción
            $stmt = $this->conn->prepare("
                INSERT INTO transacciones_transacciones 
                (usuario_id, cuenta_id, categoria_id, tipo, monto, fecha, comentario, cuenta_destino_id, es_programada, estado_activo)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, TRUE)
            ");
            
            $stmt->execute([
                $data['usuario_id'],
                $data['cuenta_id'],
                $categoriaId,
                $tipo,
                $monto,
                $data['fecha'],
                $data['comentario'] ?? null,
                $data['cuenta_destino_id'] ?? null,
                $esProgramada ? 1 : 0
            ]);
            
            $transactionId = $this->conn->lastInsertId();
            
            // Actualizar saldos de cuentas SOLO si NO es programada
            if (!$esProgramada) {
                if ($tipo === 'ingreso') {
                    $nuevoSaldo = $cuenta['saldo_actual'] + $monto;
                    $this->updateAccountBalance($data['cuenta_id'], $nuevoSaldo);
                } elseif ($tipo === 'egreso') {
                    $nuevoSaldo = $cuenta['saldo_actual'] - $monto;
                    $this->updateAccountBalance($data['cuenta_id'], $nuevoSaldo);
                } elseif ($tipo === 'transferencia') {
                    // Restar de cuenta origen
                    $nuevoSaldoOrigen = $cuenta['saldo_actual'] - $monto;
                    $this->updateAccountBalance($data['cuenta_id'], $nuevoSaldoOrigen);
                    
                    // Sumar a cuenta destino
                    $nuevoSaldoDestino = $cuentaDestino['saldo_actual'] + $monto;
                    $this->updateAccountBalance($data['cuenta_destino_id'], $nuevoSaldoDestino);
                }
                // Los ajustes se manejan con createAdjustment()
            }
            
            $this->conn->commit();
            return ['success' => true, 'id' => $transactionId];
            
        } catch (\Exception $e) {
            $this->conn->rollBack();
            error_log('Transaction::create error: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Actualizar transacción
     */
    public function update(int $id, int $userId, array $data): array {
        try {
            // Obtener transacción original
            $original = $this->getById($id, $userId);
            if (!$original) {
                return ['success' => false, 'message' => 'Transacción no encontrada'];
            }
            
            // Verificar si ya hay una transacción activa
            $tieneTransaccionActiva = $this->conn->inTransaction();
            
            if (!$tieneTransaccionActiva) {
                $this->conn->beginTransaction();
            }
            
            try {
                // Revertir saldos originales SOLO si la transacción original estaba ejecutada
                $fechaOriginal = new \DateTime($original['fecha']);
                $fechaActual = new \DateTime();
                $fechaOriginal->setTime(0, 0, 0);
                $fechaActual->setTime(0, 0, 0);
                
                // Verificar si la columna es_programada existe
                $stmt = $this->conn->query("SHOW COLUMNS FROM transacciones_transacciones LIKE 'es_programada'");
                $tieneEsProgramada = $stmt->rowCount() > 0;
                
                $originalEjecutada = false;
                if ($tieneEsProgramada) {
                    $originalEjecutada = !((bool)($original['es_programada'] ?? false)) || $fechaOriginal <= $fechaActual;
                } else {
                    $originalEjecutada = $fechaOriginal <= $fechaActual;
                }
                
                // Solo revertir si la transacción original estaba ejecutada
                if ($originalEjecutada) {
                    $this->revertTransaction($original);
                }
                
                // Preparar datos para crear nueva transacción
                $newData = [
                    'usuario_id' => $userId,
                    'cuenta_id' => $data['cuenta_id'] ?? $original['cuenta_id'],
                    'tipo' => $data['tipo'] ?? $original['tipo'],
                    'monto' => $data['monto'] ?? $original['monto'],
                    'fecha' => $data['fecha'] ?? $original['fecha'],
                    'comentario' => $data['comentario'] ?? $original['comentario'] ?? null,
                    'es_programada' => $data['es_programada'] ?? false
                ];
                
                // Categoría solo para ingresos y egresos, no para transferencias ni ajustes
                if ($newData['tipo'] !== 'transferencia' && $newData['tipo'] !== 'ajuste') {
                    $newData['categoria_id'] = $data['categoria_id'] ?? $original['categoria_id'] ?? null;
                }
                
                if (($newData['tipo'] === 'transferencia') && isset($data['cuenta_destino_id'])) {
                    $newData['cuenta_destino_id'] = $data['cuenta_destino_id'];
                } elseif (($newData['tipo'] === 'transferencia') && !empty($original['cuenta_destino_id'])) {
                    $newData['cuenta_destino_id'] = $original['cuenta_destino_id'];
                }
                
                // Determinar si es programada (fecha futura)
                $fechaTransaccion = new \DateTime($newData['fecha']);
                $fechaActual = new \DateTime();
                
                // Si la fecha es futura, es programada automáticamente
                if ($fechaTransaccion > $fechaActual) {
                    $newData['es_programada'] = true;
                } else {
                    $newData['es_programada'] = false;
                }
                
                // Validar cuenta
                $stmt = $this->conn->prepare("SELECT id, saldo_actual FROM cuentas_cuentas WHERE id = ? AND usuario_id = ? AND estado_activo = TRUE LIMIT 1");
                $stmt->execute([$newData['cuenta_id'], $userId]);
                $cuenta = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$cuenta) {
                    throw new \Exception('Cuenta no válida');
                }
                
                // Validar categoría (solo para ingresos y egresos, no para transferencias ni ajustes)
                if ($newData['tipo'] !== 'transferencia' && $newData['tipo'] !== 'ajuste' && !empty($newData['categoria_id'])) {
                    $stmt = $this->conn->prepare("SELECT id, tipo FROM categorias_categorias WHERE id = ? AND estado_activo = TRUE LIMIT 1");
                    $stmt->execute([$newData['categoria_id']]);
                    $categoria = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$categoria) {
                        throw new \Exception('Categoría no válida');
                    }
                }
                
                $monto = (float)$newData['monto'];
                $tipo = $newData['tipo'];
                
                // Para transferencias, validar cuenta destino
                if ($tipo === 'transferencia') {
                    if (empty($newData['cuenta_destino_id'])) {
                        throw new \Exception('Cuenta destino requerida para transferencias');
                    }
                    
                    $stmt = $this->conn->prepare("SELECT id, saldo_actual FROM cuentas_cuentas WHERE id = ? AND usuario_id = ? AND estado_activo = TRUE LIMIT 1");
                    $stmt->execute([$newData['cuenta_destino_id'], $userId]);
                    $cuentaDestino = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$cuentaDestino) {
                        throw new \Exception('Cuenta destino no válida');
                    }
                    
                    if ($newData['cuenta_id'] == $newData['cuenta_destino_id']) {
                        throw new \Exception('No se puede transferir a la misma cuenta');
                    }
                }
                
                // Insertar nueva transacción
                $categoriaIdForInsert = ($tipo === 'transferencia' || $tipo === 'ajuste') ? null : ($newData['categoria_id'] ?? null);
                
                $stmt = $this->conn->prepare("
                    INSERT INTO transacciones_transacciones 
                    (usuario_id, cuenta_id, categoria_id, tipo, monto, fecha, comentario, cuenta_destino_id, es_programada, estado_activo)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, TRUE)
                ");
                
                $stmt->execute([
                    $newData['usuario_id'],
                    $newData['cuenta_id'],
                    $categoriaIdForInsert,
                    $tipo,
                    $monto,
                    $newData['fecha'],
                    $newData['comentario'] ?? null,
                    $newData['cuenta_destino_id'] ?? null,
                    $newData['es_programada'] ? 1 : 0
                ]);
                
                $transactionId = $this->conn->lastInsertId();
                
                // Actualizar saldos de cuentas SOLO si NO es programada
                if (!$newData['es_programada']) {
                    if ($tipo === 'ingreso') {
                        $nuevoSaldo = $cuenta['saldo_actual'] + $monto;
                        $this->updateAccountBalance($newData['cuenta_id'], $nuevoSaldo);
                    } elseif ($tipo === 'egreso') {
                        $nuevoSaldo = $cuenta['saldo_actual'] - $monto;
                        $this->updateAccountBalance($newData['cuenta_id'], $nuevoSaldo);
                    } elseif ($tipo === 'transferencia') {
                        // Restar de cuenta origen
                        $nuevoSaldoOrigen = $cuenta['saldo_actual'] - $monto;
                        $this->updateAccountBalance($newData['cuenta_id'], $nuevoSaldoOrigen);
                        
                        // Sumar a cuenta destino
                        $nuevoSaldoDestino = $cuentaDestino['saldo_actual'] + $monto;
                        $this->updateAccountBalance($newData['cuenta_destino_id'], $nuevoSaldoDestino);
                    }
                }
                
                // Eliminar transacción original (soft delete)
                $stmt = $this->conn->prepare("UPDATE transacciones_transacciones SET estado_activo = FALSE WHERE id = ?");
                $stmt->execute([$id]);
                
                if (!$tieneTransaccionActiva) {
                    $this->conn->commit();
                }
                
                return ['success' => true, 'id' => $transactionId];
                
            } catch (\Exception $e) {
                if (!$tieneTransaccionActiva) {
                    $this->conn->rollBack();
                }
                throw $e;
            }
            
        } catch (\Exception $e) {
            error_log('Transaction::update error: ' . $e->getMessage());
            error_log('Transaction::update trace: ' . $e->getTraceAsString());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Eliminar transacción (revertir saldos)
     */
    public function delete(int $id, int $userId): array {
        try {
            $transaction = $this->getById($id, $userId);
            if (!$transaction) {
                return ['success' => false, 'message' => 'Transacción no encontrada'];
            }
            
            $this->conn->beginTransaction();
            
            // Revertir saldos
            $this->revertTransaction($transaction);
            
            // Eliminar transacción
            $stmt = $this->conn->prepare("UPDATE transacciones_transacciones SET estado_activo = FALSE WHERE id = ?");
            $stmt->execute([$id]);
            
            $this->conn->commit();
            return ['success' => true];
            
        } catch (\Exception $e) {
            $this->conn->rollBack();
            error_log('Transaction::delete error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Error al eliminar la transacción'];
        }
    }
    
    /**
     * Revertir una transacción (actualizar saldos en sentido contrario)
     * Solo revierte transacciones que estaban ejecutadas (no programadas)
     */
    private function revertTransaction(array $transaction): void {
        // Verificar si la transacción estaba ejecutada antes de revertir
        $fechaTrans = new \DateTime($transaction['fecha']);
        $fechaActual = new \DateTime();
        $fechaTrans->setTime(0, 0, 0);
        $fechaActual->setTime(0, 0, 0);
        
        // Verificar si la columna es_programada existe
        $stmt = $this->conn->query("SHOW COLUMNS FROM transacciones_transacciones LIKE 'es_programada'");
        $tieneEsProgramada = $stmt->rowCount() > 0;
        
        $estabaEjecutada = false;
        if ($tieneEsProgramada) {
            $estabaEjecutada = !((bool)($transaction['es_programada'] ?? false)) || $fechaTrans <= $fechaActual;
        } else {
            $estabaEjecutada = $fechaTrans <= $fechaActual;
        }
        
        // Solo revertir si la transacción estaba ejecutada
        if (!$estabaEjecutada) {
            error_log("Transaction::revertTransaction - Transacción no estaba ejecutada, no se revierte. ID: {$transaction['id']}, Fecha: {$transaction['fecha']}, Programada: " . ($transaction['es_programada'] ?? 'N/A'));
            return;
        }
        
        $monto = (float)$transaction['monto'];
        $tipo = $transaction['tipo'];
        
        // Obtener saldo actual de cuenta origen
        $stmt = $this->conn->prepare("SELECT saldo_actual FROM cuentas_cuentas WHERE id = ?");
        $stmt->execute([$transaction['cuenta_id']]);
        $cuenta = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$cuenta) {
            error_log("Transaction::revertTransaction - Cuenta no encontrada: {$transaction['cuenta_id']}");
            return;
        }
        
        if ($tipo === 'ingreso') {
            $nuevoSaldo = $cuenta['saldo_actual'] - $monto;
            $this->updateAccountBalance($transaction['cuenta_id'], $nuevoSaldo);
            error_log("Transaction::revertTransaction - Revertido ingreso: -$monto, nuevo saldo: $nuevoSaldo");
        } elseif ($tipo === 'egreso') {
            $nuevoSaldo = $cuenta['saldo_actual'] + $monto;
            $this->updateAccountBalance($transaction['cuenta_id'], $nuevoSaldo);
            error_log("Transaction::revertTransaction - Revertido egreso: +$monto, nuevo saldo: $nuevoSaldo");
        } elseif ($tipo === 'transferencia' && !empty($transaction['cuenta_destino_id'])) {
            // Revertir en cuenta origen (sumar)
            $nuevoSaldoOrigen = $cuenta['saldo_actual'] + $monto;
            $this->updateAccountBalance($transaction['cuenta_id'], $nuevoSaldoOrigen);
            
            // Revertir en cuenta destino (restar)
            $stmt = $this->conn->prepare("SELECT saldo_actual FROM cuentas_cuentas WHERE id = ?");
            $stmt->execute([$transaction['cuenta_destino_id']]);
            $cuentaDestino = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($cuentaDestino) {
                $nuevoSaldoDestino = $cuentaDestino['saldo_actual'] - $monto;
                $this->updateAccountBalance($transaction['cuenta_destino_id'], $nuevoSaldoDestino);
                error_log("Transaction::revertTransaction - Revertida transferencia: origen +$monto, destino -$monto");
            }
        } elseif ($tipo === 'ajuste') {
            // Para ajustes, necesitamos recalcular el saldo desde cero
            // porque los ajustes establecen el saldo directamente
            // Usaremos el Account model para recalcular
            require_once dirname(__DIR__, 2) . '/cuentas/models/Account.php';
            $accountModel = new \UI\Modules\Cuentas\Models\Account($this->conn);
            $accountModel->recalculateBalance($transaction['cuenta_id'], $transaction['usuario_id']);
            error_log("Transaction::revertTransaction - Recalculado saldo después de revertir ajuste");
        }
    }
    
    /**
     * Actualizar saldo de cuenta
     */
    /**
     * Crear transacción de ajuste de saldo
     * @param array $data Datos de la transacción
     * @param float $diferencia Diferencia entre el saldo actual y el nuevo saldo (puede ser positiva o negativa)
     */
    public function createAdjustment(array $data, float $diferencia): array {
        try {
            // Verificar si ya hay una transacción activa
            $tieneTransaccionActiva = $this->conn->inTransaction();
            
            if (!$tieneTransaccionActiva) {
                $this->conn->beginTransaction();
            }
            
            // Validar cuenta
            $stmt = $this->conn->prepare("SELECT id, saldo_actual FROM cuentas_cuentas WHERE id = ? AND usuario_id = ? AND estado_activo = TRUE LIMIT 1");
            $stmt->execute([$data['cuenta_id'], $data['usuario_id']]);
            $cuenta = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$cuenta) {
                throw new \Exception('Cuenta no válida');
            }
            
            // El monto guardado será el valor absoluto de la diferencia
            $monto = abs($diferencia);
            
            // Determinar el nuevo saldo
            if (!isset($data['nuevo_saldo']) || $data['nuevo_saldo'] === null) {
                // Si no se proporciona nuevo_saldo, calcularlo desde la diferencia
                $nuevoSaldo = $cuenta['saldo_actual'] + $diferencia;
            } else {
                $nuevoSaldo = (float)$data['nuevo_saldo'];
            }
            
            error_log("Transaction::createAdjustment - INICIO: cuenta_id={$data['cuenta_id']}, saldo_actual_antes={$cuenta['saldo_actual']}, nuevo_saldo=$nuevoSaldo, diferencia=$diferencia, monto_absoluto=$monto");
            
            // Insertar transacción de ajuste
            $stmt = $this->conn->prepare("
                INSERT INTO transacciones_transacciones 
                (usuario_id, cuenta_id, categoria_id, tipo, monto, fecha, comentario, cuenta_destino_id, es_programada, estado_activo)
                VALUES (?, ?, NULL, 'ajuste', ?, ?, ?, NULL, 0, TRUE)
            ");
            
            $stmt->execute([
                $data['usuario_id'],
                $data['cuenta_id'],
                $monto,
                $data['fecha'],
                $data['comentario'] ?? 'Ajuste de saldo'
            ]);
            
            $transactionId = $this->conn->lastInsertId();
            error_log("Transaction::createAdjustment - Transacción insertada con ID: $transactionId");
            
            // Actualizar el saldo directamente ANTES del commit
            $stmt = $this->conn->prepare("UPDATE cuentas_cuentas SET saldo_actual = ? WHERE id = ?");
            $resultadoUpdate = $stmt->execute([$nuevoSaldo, $data['cuenta_id']]);
            $filasAfectadas = $stmt->rowCount();
            error_log("Transaction::createAdjustment - UPDATE ejecutado: resultado=$resultadoUpdate, filas_afectadas=$filasAfectadas");
            
            // Verificar que se actualizó correctamente (dentro de la misma transacción)
            $stmt = $this->conn->prepare("SELECT saldo_actual FROM cuentas_cuentas WHERE id = ? LIMIT 1");
            $stmt->execute([$data['cuenta_id']]);
            $cuentaVerificada = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($cuentaVerificada) {
                $saldoVerificado = (float)$cuentaVerificada['saldo_actual'];
                error_log("Transaction::createAdjustment - Saldo verificado DENTRO de transacción: $saldoVerificado (esperado: $nuevoSaldo)");
                if (abs($saldoVerificado - $nuevoSaldo) > 0.01) {
                    error_log("Transaction::createAdjustment - ERROR: El saldo no se actualizó correctamente dentro de la transacción!");
                    throw new \Exception("Error al actualizar el saldo: esperado $nuevoSaldo, obtenido $saldoVerificado");
                }
            }
            
            if (!$tieneTransaccionActiva) {
                $this->conn->commit();
                error_log("Transaction::createAdjustment - Transacción confirmada (commit)");
                
                // Verificar nuevamente después del commit
                $stmt = $this->conn->prepare("SELECT saldo_actual FROM cuentas_cuentas WHERE id = ? LIMIT 1");
                $stmt->execute([$data['cuenta_id']]);
                $cuentaFinal = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($cuentaFinal) {
                    $saldoFinal = (float)$cuentaFinal['saldo_actual'];
                    error_log("Transaction::createAdjustment - Saldo verificado DESPUÉS de commit: $saldoFinal (esperado: $nuevoSaldo)");
                }
            }
            
            return ['success' => true, 'id' => $transactionId];
            
        } catch (\Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log('Transaction::createAdjustment error: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    private function updateAccountBalance(int $accountId, float $newBalance): void {
        $stmt = $this->conn->prepare("UPDATE cuentas_cuentas SET saldo_actual = ? WHERE id = ?");
        $stmt->execute([$newBalance, $accountId]);
    }
    
    /**
     * Obtener archivos de una transacción
     */
    public function getFiles(int $transactionId): array {
        try {
            $stmt = $this->conn->prepare("
                SELECT id, nombre_original, nombre_archivo, ruta, tipo_mime, tamano, fecha_creacion
                FROM transacciones_archivos
                WHERE transaccion_id = ?
                ORDER BY fecha_creacion ASC
            ");
            $stmt->execute([$transactionId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Transaction::getFiles error: ' . $e->getMessage());
            return [];
        }
    }
}
