## 2014-10-10

DataMapper\Data
- __before_\*() and __after_\*() change to public method
- add method toJSON()
- add static property Data::$mapper
- rename method formatProp($prop, array $prop_meta) to normalize($key, $value, array $attribute)
- rename method getProp() to get()
- rename method hasProp() to has()
- rename method setProp() to set()
- rename method setProps() to merge()
- rename method toArray() to pick()
- rename static property Data::$props_meta to Data::$attributes
- rename static property Data::$storage to Data::$service

DataMapper\Mapper
- add method getAttributes()
- add method getOption()
- add method getOptions()
- add method hasAttribute()
- add method unpack()
- remove method propsToRecord()
- remove method recordToProps()
- rename method getPropMeta() to getAttribute()
- rename method getStorage() to getService()
- rename method inspectData() to validateData()
- rename method package() to pack()

DataMapper\Types
- add UUID type
- add attribute "protected"
- rename attribute "auto_increase" to "auto_generate"

Exceptions
- remove lots of custom exceptions
- rename HTTP\Error to HTTP\Exception
- rename Service\ConnectionError to Service\ConnectionException

## 2014-11-19

DataMapper
- add attribute "deprecated"
