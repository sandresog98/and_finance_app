<?php
/**
 * Modelo de Reportes
 */

namespace UI\Modules\Reportes\Models;

use PDO;
use PDOException;

class Report {
    private PDO $conn;
    
    public function __construct(PDO $connection) {
        $this->conn = $connection;
    }
    
    /**
     * Obtener resumen de ingresos y egresos por período
     */
    public function getIncomeExpenseSummary(int $userId, string $fechaDesde, string $fechaHasta): array {
        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    tipo,
                    SUM(monto) as total
                FROM transacciones_transacciones
                WHERE usuario_id = ? 
                  AND estado_activo = TRUE
                  AND fecha BETWEEN ? AND ?
                  AND tipo IN ('ingreso', 'egreso')
                GROUP BY tipo
            ");
            $stmt->execute([$userId, $fechaDesde, $fechaHasta]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $summary = ['ingreso' => 0, 'egreso' => 0];
            foreach ($results as $row) {
                $summary[$row['tipo']] = (float)$row['total'];
            }
            
            return $summary;
        } catch (PDOException $e) {
            error_log('Report::getIncomeExpenseSummary error: ' . $e->getMessage());
            return ['ingreso' => 0, 'egreso' => 0];
        }
    }
    
    /**
     * Obtener ingresos y egresos por mes
     * Si se proporciona $mes, retorna datos solo de ese mes. Si no, retorna todos los meses del año.
     */
    public function getMonthlyData(int $userId, int $anio, ?int $mes = null): array {
        try {
            if ($mes !== null) {
                // Obtener datos de un mes específico
                $stmt = $this->conn->prepare("
                    SELECT 
                        tipo,
                        SUM(monto) as total
                    FROM transacciones_transacciones
                    WHERE usuario_id = ? 
                      AND estado_activo = TRUE
                      AND YEAR(fecha) = ?
                      AND MONTH(fecha) = ?
                      AND tipo IN ('ingreso', 'egreso')
                    GROUP BY tipo
                ");
                $stmt->execute([$userId, $anio, $mes]);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $data = ['ingreso' => 0, 'egreso' => 0];
                foreach ($results as $row) {
                    $data[$row['tipo']] = (float)$row['total'];
                }
                
                return $data;
            } else {
                // Obtener datos de todos los meses del año (comportamiento original)
                $stmt = $this->conn->prepare("
                    SELECT 
                        MONTH(fecha) as mes,
                        tipo,
                        SUM(monto) as total
                    FROM transacciones_transacciones
                    WHERE usuario_id = ? 
                      AND estado_activo = TRUE
                      AND YEAR(fecha) = ?
                      AND tipo IN ('ingreso', 'egreso')
                    GROUP BY MONTH(fecha), tipo
                    ORDER BY mes ASC
                ");
                $stmt->execute([$userId, $anio]);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $monthlyData = [];
                for ($i = 1; $i <= 12; $i++) {
                    $monthlyData[$i] = ['ingreso' => 0, 'egreso' => 0];
                }
                
                foreach ($results as $row) {
                    $mes = (int)$row['mes'];
                    $monthlyData[$mes][$row['tipo']] = (float)$row['total'];
                }
                
                return $monthlyData;
            }
        } catch (PDOException $e) {
            error_log('Report::getMonthlyData error: ' . $e->getMessage());
            return $mes !== null ? ['ingreso' => 0, 'egreso' => 0] : [];
        }
    }
    
    /**
     * Obtener gastos por categoría
     */
    public function getExpensesByCategory(int $userId, string $fechaDesde, string $fechaHasta): array {
        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    c.nombre as categoria,
                    c.icono,
                    c.color,
                    SUM(t.monto) as total
                FROM transacciones_transacciones t
                INNER JOIN categorias_categorias c ON t.categoria_id = c.id
                WHERE t.usuario_id = ? 
                  AND t.estado_activo = TRUE
                  AND t.tipo = 'egreso'
                  AND t.fecha BETWEEN ? AND ?
                GROUP BY c.id, c.nombre, c.icono, c.color
                ORDER BY total DESC
                LIMIT 10
            ");
            $stmt->execute([$userId, $fechaDesde, $fechaHasta]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Report::getExpensesByCategory error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtener ingresos por categoría
     */
    public function getIncomeByCategory(int $userId, string $fechaDesde, string $fechaHasta): array {
        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    c.nombre as categoria,
                    c.icono,
                    c.color,
                    SUM(t.monto) as total
                FROM transacciones_transacciones t
                INNER JOIN categorias_categorias c ON t.categoria_id = c.id
                WHERE t.usuario_id = ? 
                  AND t.estado_activo = TRUE
                  AND t.tipo = 'ingreso'
                  AND t.fecha BETWEEN ? AND ?
                GROUP BY c.id, c.nombre, c.icono, c.color
                ORDER BY total DESC
                LIMIT 10
            ");
            $stmt->execute([$userId, $fechaDesde, $fechaHasta]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Report::getIncomeByCategory error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtener transacciones con filtros avanzados
     */
    public function getFilteredTransactions(int $userId, array $filters): array {
        try {
            $sql = "
                SELECT t.id, t.tipo, t.monto, t.fecha, t.comentario,
                       c.nombre AS cuenta_nombre,
                       cat.nombre AS categoria_nombre, cat.icono AS categoria_icono, cat.color AS categoria_color
                FROM transacciones_transacciones t
                INNER JOIN cuentas_cuentas c ON t.cuenta_id = c.id
                INNER JOIN categorias_categorias cat ON t.categoria_id = cat.id
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
            
            $sql .= " ORDER BY t.fecha DESC, t.fecha_creacion DESC LIMIT 1000";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Report::getFilteredTransactions error: ' . $e->getMessage());
            return [];
        }
    }
}
