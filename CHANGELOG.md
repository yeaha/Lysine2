## 2014-10-10

DataMapper\Data
- add method pick()
- add method toJSON()
- add static property Data::$mapper
- remove method toArray()
- rename method formatProp($prop, array $prop_meta) to normalize($key, $value, array $attribute)
- rename method setProps() to merge()
- rename static property Data::$props_meta to Data::$attributes
- rename static property Data::$storage to Data::$service
- __before_*() and __after_*() change to public method

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
- add attribute "protected"
- rename attribute "auto_increase" to "auto_generate"

Exceptions
- remove lots of custom exceptions
- rename HTTP\Error to HTTP\Exception
- rename Service\ConnectionError to Service\ConnectionException
