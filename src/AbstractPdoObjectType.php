<?php

namespace Graphael;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use PDO;

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
    protected function getBy($key, $value)
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ' . $this->tableName . ' WHERE ' . $key . ' = :value');
        $result = $stmt->execute(['value'=>$value]);
        return $this->processRow($stmt->fetch(PDO::FETCH_ASSOC));
    }

    protected function getAllBy($key, $value)
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
}
