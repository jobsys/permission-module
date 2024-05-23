<?php

namespace Modules\Permission\Enums;

enum Scope: int
{

	case CUSTOM = -1; //自定义
    case NONE = 0; //不可见
    case SELF = 1; //本人创建
    case DEPARTMENT = 2; //本部门
    case DEPARTMENT_AND_SUBORDINATE = 4; //本部门及子部门
    case ALL = 8; //全部数据

}
