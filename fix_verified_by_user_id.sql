-- Sentencia SQL para asignar verified_by_user_id a registros de current_accounts
-- que son tipo 'credit', tienen status 'OK' y no tienen verified_by_user_id asignado
-- Se asigna el internal_user_id del customer asociado como verified_by_user_id

UPDATE current_accounts ca
INNER JOIN customers c ON ca.customer_id = c.id
SET 
    ca.verified_by_user_id = c.internal_user_id,
    ca.verified_at = NOW()
WHERE 
    ca.type = 'credit'
    AND ca.status = 'OK'
    AND ca.verified_by_user_id IS NULL
    AND c.internal_user_id IS NOT NULL;

-- Verificar cuántos registros se actualizaron (ejecutar después del UPDATE)
-- SELECT COUNT(*) as registros_actualizados
-- FROM current_accounts ca
-- INNER JOIN customers c ON ca.customer_id = c.id
-- WHERE 
--     ca.type = 'credit'
--     AND ca.status = 'OK'
--     AND ca.verified_by_user_id IS NOT NULL;
