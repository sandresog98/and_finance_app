<?php
/**
 * AND FINANCE APP - Modelo de Presupuestos
 * Gestión de presupuestos mensuales por categoría
 */

require_once __DIR__ . '/../../../../config/database.php';

class PresupuestoModel {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Obtener todos los presupuestos del usuario para un mes/año específico
     */
    public function getAllByUserPeriodo(int $userId, int $mes, int $anio): array {
        $stmt = $this->db->prepare("
            SELECT p.*, 
                   c.nombre as categoria_nombre, 
                   c.icono as categoria_icono, 
                   c.color as categoria_color,
                   c.tipo as categoria_tipo,
                   COALESCE(
                       (SELECT SUM(t.monto) 
                        FROM transacciones t 
                        WHERE t.categoria_id = p.categoria_id 
                        AND t.usuario_id = p.usuario_id
                        AND t.tipo = 'egreso'
                        AND t.estado = 1
                        AND t.realizada = 1
                        AND MONTH(t.fecha_transaccion) = p.mes
                        AND YEAR(t.fecha_transaccion) = p.anio
                       ), 0
                   ) as monto_gastado
            FROM presupuestos p
            JOIN categorias c ON p.categoria_id = c.id
            WHERE p.usuario_id = :usuario_id 
            AND p.mes = :mes 
            AND p.anio = :anio
            AND p.estado = 1
            ORDER BY c.nombre ASC
        ");
        $stmt->execute([
            'usuario_id' => $userId,
            'mes' => $mes,
            'anio' => $anio
        ]);
        return $stmt->fetchAll();
    }

    /**
     * Obtener un presupuesto por ID
     */
    public function getById(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT p.*, 
                   c.nombre as categoria_nombre, 
                   c.icono as categoria_icono, 
                   c.color as categoria_color
            FROM presupuestos p
            JOIN categorias c ON p.categoria_id = c.id
            WHERE p.id = :id
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Verificar si ya existe presupuesto para una categoría en un período
     */
    public function existePresupuesto(int $userId, int $categoriaId, int $mes, int $anio, ?int $excludeId = null): bool {
        $sql = "
            SELECT COUNT(*) FROM presupuestos 
            WHERE usuario_id = :usuario_id 
            AND categoria_id = :categoria_id 
            AND mes = :mes 
            AND anio = :anio
            AND estado = 1
        ";
        $params = [
            'usuario_id' => $userId,
            'categoria_id' => $categoriaId,
            'mes' => $mes,
            'anio' => $anio
        ];

        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Crear un nuevo presupuesto
     */
    public function create(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO presupuestos (
                usuario_id, categoria_id, monto_limite, mes, anio, alertar_al, estado
            ) VALUES (
                :usuario_id, :categoria_id, :monto_limite, :mes, :anio, :alertar_al, 1
            )
        ");

        $stmt->execute([
            'usuario_id' => $data['usuario_id'],
            'categoria_id' => $data['categoria_id'],
            'monto_limite' => $data['monto_limite'],
            'mes' => $data['mes'],
            'anio' => $data['anio'],
            'alertar_al' => $data['alertar_al'] ?? 80
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Actualizar un presupuesto existente
     */
    public function update(int $id, array $data): bool {
        $stmt = $this->db->prepare("
            UPDATE presupuestos SET
                categoria_id = :categoria_id,
                monto_limite = :monto_limite,
                alertar_al = :alertar_al,
                fecha_actualizacion = CURRENT_TIMESTAMP
            WHERE id = :id
        ");

        return $stmt->execute([
            'id' => $id,
            'categoria_id' => $data['categoria_id'],
            'monto_limite' => $data['monto_limite'],
            'alertar_al' => $data['alertar_al'] ?? 80
        ]);
    }

    /**
     * Eliminar un presupuesto (soft delete)
     */
    public function delete(int $id): bool {
        $stmt = $this->db->prepare("UPDATE presupuestos SET estado = 0 WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Copiar presupuestos de un mes a otro
     */
    public function copiarPresupuestos(int $userId, int $mesOrigen, int $anioOrigen, int $mesDestino, int $anioDestino): int {
        // Obtener presupuestos del mes origen
        $presupuestosOrigen = $this->getAllByUserPeriodo($userId, $mesOrigen, $anioOrigen);
        $copiados = 0;

        foreach ($presupuestosOrigen as $presupuesto) {
            // Verificar que no exista ya en el destino
            if (!$this->existePresupuesto($userId, $presupuesto['categoria_id'], $mesDestino, $anioDestino)) {
                $this->create([
                    'usuario_id' => $userId,
                    'categoria_id' => $presupuesto['categoria_id'],
                    'monto_limite' => $presupuesto['monto_limite'],
                    'mes' => $mesDestino,
                    'anio' => $anioDestino,
                    'alertar_al' => $presupuesto['alertar_al']
                ]);
                $copiados++;
            }
        }

        return $copiados;
    }

    /**
     * Obtener resumen de presupuestos del mes
     */
    public function getResumenMes(int $userId, int $mes, int $anio): array {
        $presupuestos = $this->getAllByUserPeriodo($userId, $mes, $anio);
        
        $totalPresupuestado = 0;
        $totalGastado = 0;
        $alertas = [];

        foreach ($presupuestos as $p) {
            $totalPresupuestado += $p['monto_limite'];
            $totalGastado += $p['monto_gastado'];
            
            $porcentaje = $p['monto_limite'] > 0 ? ($p['monto_gastado'] / $p['monto_limite']) * 100 : 0;
            
            if ($porcentaje >= 100) {
                $alertas[] = [
                    'tipo' => 'danger',
                    'categoria' => $p['categoria_nombre'],
                    'mensaje' => 'Presupuesto excedido',
                    'porcentaje' => round($porcentaje, 1)
                ];
            } elseif ($porcentaje >= $p['alertar_al']) {
                $alertas[] = [
                    'tipo' => 'warning',
                    'categoria' => $p['categoria_nombre'],
                    'mensaje' => 'Cerca del límite',
                    'porcentaje' => round($porcentaje, 1)
                ];
            }
        }

        return [
            'total_presupuestado' => $totalPresupuestado,
            'total_gastado' => $totalGastado,
            'disponible' => $totalPresupuestado - $totalGastado,
            'porcentaje_usado' => $totalPresupuestado > 0 ? round(($totalGastado / $totalPresupuestado) * 100, 1) : 0,
            'presupuestos' => $presupuestos,
            'alertas' => $alertas,
            'cantidad' => count($presupuestos)
        ];
    }

    /**
     * Obtener categorías de egreso sin presupuesto para un período
     */
    public function getCategoriasDisponibles(int $userId, int $mes, int $anio): array {
        $stmt = $this->db->prepare("
            SELECT c.id, c.nombre, c.icono, c.color
            FROM categorias c
            WHERE c.tipo = 'egreso'
            AND c.estado = 1
            AND (c.usuario_id = :usuario_id OR c.es_sistema = 1)
            AND c.id NOT IN (
                SELECT categoria_id FROM presupuestos 
                WHERE usuario_id = :usuario_id2 
                AND mes = :mes 
                AND anio = :anio
                AND estado = 1
            )
            ORDER BY c.nombre ASC
        ");
        $stmt->execute([
            'usuario_id' => $userId,
            'usuario_id2' => $userId,
            'mes' => $mes,
            'anio' => $anio
        ]);
        return $stmt->fetchAll();
    }
}

