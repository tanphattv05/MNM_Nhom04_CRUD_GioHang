<?php
require dirname(__DIR__).'/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__).'/.env');
$kernel = new App\Kernel('dev', false);
$kernel->boot();
$db = $kernel->getContainer()->get('doctrine')->getConnection();
$tables = ['THANH_VIEN','GIO_HANG','QTV','MUA','SACH','LOAI_SACH'];
$schema = ['database'=>(string)$db->fetchOne('SELECT DB_NAME()'), 'verified_on'=>(new DateTimeImmutable('now', new DateTimeZone('Asia/Ho_Chi_Minh')))->format('Y-m-d'), 'tables'=>[], 'relationships'=>[]];
foreach ($tables as $table) {
    $rows = $db->fetchAllAssociative("SELECT c.name, ty.name AS type, CASE WHEN ty.name IN ('nvarchar','nchar') THEN c.max_length / 2 ELSE c.max_length END AS length, c.precision, c.scale, c.is_nullable, c.is_identity,
        CASE WHEN EXISTS (SELECT 1 FROM sys.indexes i JOIN sys.index_columns ic ON ic.object_id = i.object_id AND ic.index_id = i.index_id WHERE i.object_id = c.object_id AND ic.column_id = c.column_id AND i.is_primary_key = 1) THEN 1 ELSE 0 END AS is_primary_key,
        CASE WHEN EXISTS (SELECT 1 FROM sys.foreign_key_columns fk WHERE fk.parent_object_id = c.object_id AND fk.parent_column_id = c.column_id) THEN 1 ELSE 0 END AS is_foreign_key,
        CASE WHEN EXISTS (SELECT 1 FROM sys.indexes i JOIN sys.index_columns ic ON ic.object_id = i.object_id AND ic.index_id = i.index_id WHERE i.object_id = c.object_id AND ic.column_id = c.column_id AND i.is_unique_constraint = 1) THEN 1 ELSE 0 END AS is_unique
        FROM sys.columns c JOIN sys.types ty ON ty.user_type_id = c.user_type_id WHERE c.object_id = OBJECT_ID(?) ORDER BY c.column_id", ['dbo.'.$table]);
    foreach ($rows as &$row) {
        foreach (['is_nullable','is_identity','is_primary_key','is_foreign_key','is_unique'] as $flag) {
            $row[$flag] = (bool)(int)$row[$flag];
        }
        $row['sql_type'] = strtoupper($row['type']);
        if (in_array($row['type'], ['varchar','nvarchar','char','nchar'], true)) { $row['sql_type'] .= '('.$row['length'].')'; }
        if (in_array($row['type'], ['numeric','decimal'], true)) { $row['sql_type'] .= '('.$row['precision'].','.$row['scale'].')'; }
    }
    unset($row);
    $schema['tables'][$table] = $rows;
}
$schema['relationships'] = $db->fetchAllAssociative("SELECT pt.name AS child_table, pc.name AS child_column, rt.name AS parent_table, rc.name AS parent_column, pc.is_nullable AS optional_parent FROM sys.foreign_key_columns fkc JOIN sys.tables pt ON pt.object_id = fkc.parent_object_id JOIN sys.columns pc ON pc.object_id = fkc.parent_object_id AND pc.column_id = fkc.parent_column_id JOIN sys.tables rt ON rt.object_id = fkc.referenced_object_id JOIN sys.columns rc ON rc.object_id = fkc.referenced_object_id AND rc.column_id = fkc.referenced_column_id WHERE pt.schema_id = SCHEMA_ID('dbo') AND pt.name IN ('SACH','MUA','GIO_HANG') ORDER BY pt.name, pc.name");
foreach ($schema['relationships'] as &$relationship) { $relationship['optional_parent'] = (bool)(int)$relationship['optional_parent']; }
unset($relationship);
if (count($schema['relationships']) !== 6) { throw new RuntimeException('Expected six foreign-key relationships.'); }
$file = dirname(__DIR__).'/docs/diagrams/database-schema.json';
file_put_contents($file, json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
echo "Exported metadata only: ", count($schema['tables']), " tables, ", count($schema['relationships']), " foreign keys.\n";
$kernel->shutdown();
