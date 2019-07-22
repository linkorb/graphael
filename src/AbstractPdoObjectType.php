<?php

namespace Graphael;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use PDO;
use PDOException;

abstract class AbstractPdoObjectType extends ObjectType
{
    protected $pdo;
    protected $tableName;

    public function __construct(PDO $pdo, $config)
    {
        $this->pdo = $pdo;
        parent::__construct($config);
    }

    // TODO: rename to getOneBy
    public function getBy($key, $value)
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ' . $this->tableName . ' WHERE ' . $key . ' = :value');
        $result = $stmt->execute(['value'=>$value]);
        return $this->processRow($stmt->fetch(PDO::FETCH_ASSOC));
    }

    public function getAllBy($key, $value)
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ' . $this->tableName . ' WHERE ' . $key . ' = :value');
        $result = $stmt->execute(['value'=>$value]);
        return $this->processRows($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function getAll()
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ' . $this->tableName . ';');
        $result = $stmt->execute();
        return $this->processRows($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    protected function processRows($rows)
    {
        $res = [];
        foreach ($rows as $i=>$row) {
            $res[$i] = $this->processRow($row);
        }
        return $res;
    }

    protected function processRow($row)
    {
        if (!is_array($row)) {
            return $row;
        }
        $res = [];
        foreach($row as $key=>$value) {
            $res[$this->camelize($key)]=$value;
        }
        return $res;
    }

    public function classify($word)
    {
        return str_replace(' ', '', ucwords(strtr($word, '_-', '  ')));
    }

    public function camelize($word)
    {
        return lcfirst($this->classify($word));
    }

    public static function underscore($id)
    {
        return strtolower(preg_replace(array('/([A-Z]+)([A-Z][a-z])/', '/([a-z\d])([A-Z])/'), array('\\1_\\2', '\\1_\\2'), str_replace('_', '.', $id)));
    }


    public function insert($values)
    {
        $setters = $this->arrayToSetters($values);

        $sql = sprintf(
            "INSERT INTO %s SET %s",
            $this->tableName,
            $setters
        );
        $statement = $this->pdo->prepare($sql);
        $statement->execute($values);
        return $this->pdo->lastInsertId();
    }

    public function update($keys, $values): int
    {
        $where = $this->arrayToWhere($keys);
        $setters = $this->arrayToSetters($values);
        $sql = sprintf(
            "UPDATE %s SET %s WHERE %s",
            $this->tableName,
            $setters,
            $where
        );
        $statement = $this->pdo->prepare($sql);

        if (!$statement->execute(array_merge($values, $keys))) {
            throw new PDOException('Wrong SQL query passed', 1);
        }

        return $statement->rowCount();
    }

    protected function count($keys)
    {
        $where = $this->arrayToWhere($keys);

        $sql = sprintf(
            "SELECT count(*) AS c FROM %s WHERE %s",
            $this->tableName,
            $where
        );
        $statement = $this->pdo->prepare($sql);
        $statement->execute($keys);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row['c'];
    }

    public function upsert($keys, $values)
    {
        $count = $this->count($keys);
        if ($count>0) {
            $this->update($keys, $values);
        } else {
            $this->insert(array_merge($keys, $values));
        }
    }

    public function delete($keys)
    {
        $where = $this->arrayToWhere($keys);

        $sql = sprintf(
            "DELETE FROM %s WHERE %s",
            $this->tableName,
            $where
        );
        $statement = $this->pdo->prepare($sql);
        $statement->execute($keys);
    }

    protected function arrayToWhere($a) {
        $sql = '';
        foreach ($a as $key => $value) {
            if ($sql != '') {
                $sql .= ' AND ';
            }
            $sql .= sprintf('%s=:%s', $key, $key);
        }
        return $sql;
    }

    protected function arrayToSetters($a) {
        $sql = '';
        foreach ($a as $key => $value) {
            if ($sql != '') {
                $sql .= ', ';
            }
            $sql .= sprintf('%s=:%s', $this->underscore($key), $key);
        }
        return $sql;
    }
}
