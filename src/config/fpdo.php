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
    'xml_attributes' => 'nodeAttributes',

    /**
     * Using \FpdoFpdo::dump you can choise how many insert rows will be used
     * on a single inssert statement
     */
    'dump'=>[
        'insert_limit'=>5
    ],

    'log' => [
        
        /**
         * activating logs
         * 
         * a log appears as it
         * 
         * [YYY-MM-DD HH:II:SS] local.INFO query taken <time> : <query_str>
         * [YYY-MM-DD HH:II:SS] local.INFO read <database.table taken <time> to be readed.
         * [YYY-MM-DD HH:II:SS] local.INFO write <database.table taken <time> to be wrote.
         */
        'enabled' => true,
        // the file where logs will be stored
        'file' => storage_path('logs/fpdo.log'),

        /**
         * activating query logs, each query will be executed will be logged, according to its max_execution_time value
         * it means that if you want to log all queries longer then 1 sec, the max_execution_time should be 1000
         * to log ALL queries simply set it to -1
         */
        'query' => [
            'enabled' => true,
            'max_execution_time' => 20 // milliseconds
        ],   
        /**
         * activating read logs, each resource reading and parsing will be logged, according to its max_execution_time value
         * it means that if you want to log all resource reading then 1 sec, the max_execution_time should be 1000
         * to log ALL resource reading simply set it to -1
         */
        'read' => [
            'enabled' => true,
            'max_execution_time' => 20 // milliseconds
        ],        
        /**
         * activating write logs, each resource writing will be logged, according to its max_execution_time value
         * it means that if you want to log all resource writing then 1 sec, the max_execution_time should be 1000
         * to log ALL resource writing simply set it to -1
         */
        'write' => [
            'enabled' => true,
            'max_execution_time' => 20 // milliseconds
        ]
    ]
    
];