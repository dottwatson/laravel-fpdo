<?php

return [
    /**
     * the default charset used on tables where not defined
     */
    'default_charset' => 'utf8mb4',

    /**
     * when  resource is evalutated, the ssystem tries to guess the columns types by examinating the data.
     * It happens only when non schema is set for the source. This can be changed here.
     * 
     * If true, the data will be parsed and the system tries to detect the data type, creating the table columns according to.
     * If false, all the columns will be created as TEXT columns and nullable.
     * 
     * Note that if there isn't data to parse, the column will have TEXT as type and is nullable
     * 
     * If enabled it will spend time to works, but the data will be set correctly according with its type.
     * More data to check meand more accuracy for column type detection
     */
    'guess_datatype' => true,

    /**
     * When the systems has not the ability to set a column name (e.g. a csv without header and no schema defined for table)
     * It creates table columns with an arbitrary name. 
     * By default the column name is `column<n>` , where n is the colun position in dataset.
     * 
     * the logic can not be altered, except the prefix on column name, tha is `column`
     * 
     * setting this value as 'myColumn', the not devined column name in dataset will be `myColumn<n>'
     */
    'default_column' => 'column',



    /**
     * When a resource is an XML, the system creates also a column for store the attributes.
     * Here you can change the column Name
     */
    'xml_attributes' => 'nodeAttributes'
    
];