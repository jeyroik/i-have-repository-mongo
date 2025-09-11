<?php
namespace jeyroik\components\repositories;

use jeyroik\interfaces\IHaveAttributes;
use jeyroik\interfaces\attributes\IHaveId;
use MongoDB\Collection;

class RepositoryMongo extends Repository
{
    protected const MONGO__ID = '_id';
    protected const MONGO__SKIP = 'skip';
    protected const MONGO__LIMIT = 'limit';
    protected const MONGO__SORT = 'sort';
    protected const MONGO__SET = '$set';

    protected $mongo = null;

    /**
     * @var ?Collection
     */
    protected $collection = null;

    protected function init(): void
    {
        //MONGO__DSN = mongodb://developer:password@localhost:27017/
        $this->mongo = new \MongoDB\Client(MONGO__DSN . '/' . $this->getDbName());
        $this->collection = $this->mongo->{$this->getDbName()}->{$this->getTableName()};
    }

    public function findOne(array $where = [], array $orderBy = [], int $offset = 0): ?IHaveAttributes
    {
        $options = [
            static::MONGO__SKIP => $offset,
            static::MONGO__SORT => $this->prepareOrderBy($orderBy)
        ];
        $record = $this->collection->findOne($this->prepareWhere($where), $options);

        if ($record) {
            $asArray = $record->getArrayCopy();
            $asArray[static::MONGO__ID] = (string) $asArray[static::MONGO__ID];
            $itemClass = $this->getItemClass();
            return new $itemClass($this->unSerializeItem($asArray));
        }

        return null;
    }

    public function findAll(array $where = [], array $orderBy = [], int $offset = 0, int $limit = 0): array
    {
        $options = [
            static::MONGO__SKIP => $offset,
            static::MONGO__LIMIT => $limit,
            static::MONGO__SORT => $this->prepareOrderBy($orderBy)
        ];
        $itemClass = $this->getItemClass();
        $rawRecords = $this->collection->find($this->prepareWhere($where), $options);
        $records = [];

        foreach ($rawRecords as $record) {
            $record = $this->unSerializeItem($record);
            $record[static::MONGO__ID] = (string)$record[static::MONGO__ID]['oid'];
            
            $records[] = new $itemClass($record);
        }

        return $records;
    }

    public function updateOne(IHaveId $item): void
    {
        if (isset($item[static::MONGO__ID])) {
            unset($item[static::MONGO__ID]);
        }

        $result = $this->collection->updateOne(
            [IHaveId::FIELD__ID => $item[IHaveId::FIELD__ID]], 
            [static::MONGO__SET => $this->applyPlugins('updateOne', $item->__toArray())]
        );

        if (!$result->getModifiedCount()) {
            throw new \Exception('Nothing to update');
        }
    }

    public function updateMany(array $where, array $values): void
    {
        $result = $this->collection->updateOne(
            $this->prepareWhere($where), 
            $values
        );

        if (!$result->getModifiedCount()) {
            throw new \Exception('Nothing to update');
        }
    }

    public function deleteOne(IHaveId $item): void
    {
        $result = $this->collection->deleteOne([IHaveId::FIELD__ID => $item[IHaveId::FIELD__ID]]);

        if (!$result->getDeletedCount()) {
            throw new \Exception('Nothing to delete');
        }
    }

    public function deleteMany(array $where): void
    {
        $result = $this->collection->deleteMany($this->prepareWhere($where));

        if (!$result->getDeletedCount()) {
            throw new \Exception('Nothing to delete');
        }
    }

    public function insertOne(array $data): ?IHaveAttributes
    {
        $data = $this->applyPlugins('insertOne', $data);
        $result = $this->collection->insertOne($data);

        if ($result->getInsertedCount()) {
            $data[static::MONGO__ID] = $result->getInsertedId();
            $itemClass = $this->getItemClass();
            return new $itemClass($data);
        }

        throw new \Exception('Can not insert a record');
    }

    public function insertMany(array $items): array
    {
        $result = $this->collection->insertMany($items);
        $objects = [];

        if ($result->getInsertedCount()) {
            $ids = $result->getInsertedIds();
            foreach ($items as $i => $item) {
                $items[$i][static::MONGO__ID] = $ids[$i] ?? '';
                $itemClass = $this->getItemClass();
                $objects[] = new $itemClass($items[$i]);
            }
        }

        return $objects;
    }

    public function truncate(): void
    {
        $this->collection->drop();
    }

    protected function prepareWhere(array $where): array
    {
        $map = $this->getWhereOperationsMap();
        $preparedWhere = [];

        foreach ($where as $field => $cond) {
            if (!is_array($cond)) {
                $preparedWhere[$field] = $cond;
                continue;
            }

            $preparedWhere[$field] = [];

            foreach ($cond as $key => $value) {
                if (in_array($key, static::ALL_OPERATIONS)) {
                    list($operation, $preparedValue) = $map[$key]($value);
                    $preparedWhere[$field][$operation] = $preparedValue;
                } else {
                    throw new \Exception('Incorrect where condition');
                }
            }
        }

        return $preparedWhere;
    }

    protected function getWhereOperationsMap(): array
    {
        return [
            self::NOT_EQUAL => function ($value): array {
                return ['$ne', $value];
            },
            self::GREATER => function ($value): array {
                return ['$gt', $value];
            },
            self::GREATER_OR_EQUAL => function ($value): array {
                return ['$gte', $value];
            },
            self::LOWER => function ($value): array {
                return ['$lt', $value];
            },
            self::LOWER_OR_EQUAL => function ($value): array {
                return ['$lte', $value];
            },
            self::IN => function ($value): array {
                return ['$in', $value];
            },
            self::NOT_IN => function ($value): array {
                return ['$nin', $value];
            },
            self::LIKE => function ($value): array {
                return ['$regex', '/' . $value . '/'];
            }
        ];
    }

    protected function prepareOrderBy(array $orderBy): array
    {
        foreach ($orderBy as $field => $dir) {
            $orderBy[$field] = $dir == static::ORDER__ASC ? 1 : -1;
        }

        return $orderBy;
    }

    protected function unSerializeItem($item): array
    {
        $unSerialized = [];

        $item = (array) $item;

        foreach ($item as $field => $value) {

            if (is_object($value)) {
                $value = $this->unSerializeItem($value);
            }

            $unSerialized[$field] = $value;
        }

        return $unSerialized;
    }
}
