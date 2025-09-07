# i-have-repository-mongo
MognoDB driver for the "I have repository" library

# usage

## set env

- MONGO__DSN : set to string like `mongodb://developer:password@localhost:27017/`
- DB__CLASS : set to `jeyroik\components\repositories\RepositoryMongo`
    - Instead of env you can directly set a driver: `$this->getRepo(Some::class, dbClass: jeyroik\components\repositories\RepositoryMongo::class)`.

## code example

See in the `jeyroik/i-have-repository` [README](https://github.com/jeyroik/i-have-repository/blob/master/README.md).
