<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted'             => ' :attribute 为必填',
    'active_url'           => ' :attribute 是不可用的链接',
    'after'                => ' :attribute 必须为一个在 :date  之后的时间',
    'after_or_equal'       => ' :attribute 必须为一个在 :date 之后的时间或等于 :date 当前时间',
    'alpha'                => ' :attribute 只允许全部为字母',
    'alpha_dash'           => ' :attribute 只允许字母，数字和_连接符',
    'alpha_num'            => ' :attribute 只允许字母和数字',
    'array'                => ' :attribute 必须为数组',
    'before'               => ' :attribute 必须为一个在 :date 之前的时间',
    'before_or_equal'      => ' :attribute 必须为一个在 :date 之前的时间或等于 :date 当前时间',
    'between'              => [
        'numeric' => ' :attribute 必须在 :min 至 :max 之间',
        'file'    => ' :attribute 必须限制在 :min 至 :max k',
        'string'  => ' :attribute 必须在 :min 至 :max 字符',
        'array'   => ' :attribute 必须包含 :min 至 :max 键',
    ],
    'boolean'              => ' :attribute 字段必须为 true 或 false',
    'confirmed'            => ' :attribute 确认不匹配',
    'date'                 => ' :attribute 是一个不可用的时间',
    'date_format'          => ' :attribute 不匹配: format :format',
    'different'            => ' :attribute 和 :other 必须不同',
    'digits'               => ' :attribute 必须 :digits 数值',
    'digits_between'       => ' :attribute 必须在 :min 至 :max 数值之间',
    'dimensions'           => ' :attribute 有无效的图片尺寸',
    'distinct'             => ' :attribute 字段具有重复的值',
    'email'                => ' :attribute 必须为可用的邮箱地址',
    'exists'               => ' 选中的 :attribute 不可用',
    'file'                 => ' :attribute 必须为一个文件',
    'filled'               => ' :attribute 字段必填',
    'image'                => ' :attribute 必须为图片',
    'in'                   => ' 选中的 :attribute 不可以',
    'in_array'             => ' :attribute 字段未出现在 :other 其中',
    'integer'              => ' :attribute 必须为整数',
    'ip'                   => ' :attribute 必须为合法的IP地址',
    'ipv4'                 => ' :attribute 必须为合法的IPv4地址',
    'ipv6'                 => ' :attribute 必须为合法的IPv6地址',
    'json'                 => ' :attribute 必须为合法的JSON格式数据',
    'max'                  => [
        'numeric' => ' :attribute 不可以比 :max 大',
        'file'    => ' :attribute 不能超过 :max 千字节',
        'string'  => ' :attribute 不可超过 :max 个字符',
        'array'   => ' :attribute 不能超过 :max 个键值对',
    ],
    'mimes'                => ' :attribute 必须为: :values 类型的文件',
    'min'                  => [
        'numeric' => ' :attribute 必须最小为 :min',
        'file'    => ' :attribute 必须至少 :min 千字节',
        'string'  => ' :attribute 必须至少 :min 字符',
        'array'   => ' :attribute 必须至少包含 :min 个键值对',
    ],
    'not_in'               => ' 选中的 :attribute 不合法',
    'numeric'              => ' :attribute 必须为数字',
    'present'              => ' :attribute 必须出现',
    'regex'                => ' :attribute 格式不合规范',
    'required'             => ' :attribute 字段必填',
    'required_if'          => ' :attribute 字段必填当 :other 即 :value',
    'required_unless'      => ' :attribute 必填除非 :other 在 :values 其中',
    'required_with'        => ' :attribute 必填当 :values 出现',
    'required_with_all'    => ' :attribute 字段必填当 :values 出现',
    'required_without'     => ' :attribute 字段必填当 :values 没有出现',
    'required_without_all' => ' :attribute 字段必填当 :values 无一可用',
    'same'                 => ' :attribute 和 :other 必须保持一致',
    'size'                 => [
        'numeric' => ' :attribute 必须 :size',
        'file'    => ' :attribute 必须包含 :size 千字节',
        'string'  => ' :attribute 必须包含 :size 字符',
        'array'   => ' :attribute 必须包含 :size 键',
    ],
    'string'               => ' :attribute 必须为字符',
    'timezone'             => ' :attribute 时区必须为合理的时区',
    'unique'               => ' :attribute 已经被占用，请更换',
    'url'                  => ' :attribute 格式不可用',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    |  following language lines are used to swap attribute place-holders
    | with something more reader friendly such as E-Mail Address instead
    | of "email". This simply helps us make messages a little cleaner.
    |
    */

    'attributes' => [],

];
