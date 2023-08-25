# **Permission** 权限模块 **(Required)**
该模块主是基于 RBAC（角色权限管理）的权限管理系统，包括操作权限、菜单/按钮权限、数据权限。

## 模块安装

```bash
# 安装依赖
composer require jobsys/permission-module --dev

# 启用模块
php artisan module:enable Permission && php artisan module:publish-migration Permission && php artisan migrate
```

### 配置

#### 模块配置 `config/module.php`

```php
"Permission" => [
     "route_prefix" => "manager",                                                   // 路由前缀
     "permissions" => [                                                             // 权限列表
         "page.manager.dashboard" => "工作台",                                       // 页面权限
         "page.manager.permission.role" => [                                        
             "display_name" => "角色管理",                                           // 页面权限名称（与菜单操持一致)
             "children" => [
                 "api.manager.permission.role.edit" => "编辑角色",                   // 操作权限
             ]
         ],
     ],
     "data_scope" => [                                                              // 数据权限
         "department_key" => "department_id",                                       // 部门字段名称， 默认为 department_id
         "creator_key" => "creator_id",                                             // 创建人字段名称， 默认为 creator_id
         "resources" => [                                                           // 资源列表   
             ['displayName' => '部门数据', 'name' => 'department', 'model' => \App\Models\Department::class], // 部门数据权限
         ]
     ]
 ]
```

#### `spatie/laravel-permission` 配置

```bash
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
```

> 具体配置查看 [spatie/laravel-permission](https://spatie.be/docs/laravel-permission/v5/introduction)



## 模块功能

### 操作权限
操作权限是基于 `API` 请求的权限，以 `API` 的 `name` 为权限标识，如 `API` 的 `name` 为 `user.index`，则该 `API` 的权限标识为 `api.user.index`。
如果用户请求了没有权限的 `API`, 系统将返回 `403`。其中没有配置在权限列表中的 `API` 默认无需权限即可访问。

#### 开发规范
1. 进行正常的 `API` 开发，并在 `routes/api.php` 中定义 `API` 路由，正确设置 `name` 属性。

    ```php
    Route::prefix("manager")->name("api.manager.")->group(function () {
        Route::post('/user', [UserController::class, 'edit'])->name('user.edit');
    });
    ```

2. 在 `config/module.php` 中的 `Permission => permissions` 中定义 `API` 权限。后台的权限管理页面将根据该配置进行展示。

    ```php
    'permissions' => [
         "page.manager.user" => [
                "display_name" => "账号管理",
                "children" => [
                    "api.manager.user.edit" => "编辑账号",
                    "api.manager.user.items" => "查看账号列表",
                    "api.manager.user.item" => "查看账号详情",
                    "api.manager.user.delete" => "删除账号",
                ]
         ],
    ],
    ```
3. 将权限同步到数据库
```bash
php artisan permission:sync
```

> 在 `Modules/Permission/DatabaseSeeder.php` 中定义了默认的超管角色及超管用户，可以直接初始化或者是在 `database/seeders/DatabaseSeeder.php` 中调用该 Seeder 进行初始化。
> ```bash
> php artisan module:seed Permission            # 直接初始化
> php artisan db:seed --class=DatabaseSeeder    # 在 `database/seeders/DatabaseSeeder.php` 中调用，统一初始化
> ```

### 菜单/按钮权限
菜单/按钮权限是基于 `操作权限` 的权限，即用户需要拥有某个 `API` 的权限才能看到该 `API` 对应的菜单或按钮。每个按钮对应一个或多个`操作权限`，一个菜单下有多个功能按钮，如果该用户没有拥有该菜单下的任何一个功能权限，
则该菜单对于当前用户不可见。

#### 开发规范
1. 菜单的可见性可以直接由 `API` 的权限控制，即用户需要拥有某个 `API` 的权限才能看到该 `API` 对应的菜单或按钮，无需要额外的配置。
2. 页面上`功能按钮`的可以见性由 `v-auth` 指令控制。

> `v-auth` 的三种使用方式
> 1. 使用`v-auth`指令: 如 `v-auth="'api.manager.user.edit'"`。
> 2. 使用全局的 `$auth` 方法：如 `v-if="$auth('api.manager.user.edit')"`。
> 3. 使用 `auth` 方法：在 `setup` 中进行注入 `const auth = inject('auth')`;`const isVisible = auth('api.manager.user.edit')`。

```vue
<NewbieButton v-if="$auth('api.manager.user.edit')" type="primary" icon="PlusOutlined" @click="onEdit(false)">新增账号</NewbieButton>
```

### 数据权限
数据权限是基于 `Model` （数据模型）进行定义，通过 Laravel 的 [Query Scope](https://laravel.com/docs/10.x/eloquent#query-scopes) 对数据进行权限控制。

#### 开发规范
1. 数据权限的权限范围分为
    + `NONE`: 数据不可见
    + `SELF`: 只能查看自己的数据
    + `DEPARTMENT`: 可以查看本部门的数据
    + `DEPARTMENT_AND_SUBORDINATE`: 可以查看本部门及下属部门的数据
    + `ALL`: 可以查看所有数据

   所以使用数据权限控制的 `Model` 需要有 `department_id` 字段（用于区分数据的所属部门）以及 `creator_id`（用于标记创建者）。

2. 在 Model 中添加 Trait `Modules\Permission\Traits\Authorisations;`

   ```php
   use Illuminate\Database\Eloquent\Relations\BelongsTo;
   use Modules\Permission\Traits\Authorisations;
   use Modules\Starter\Entities\BaseModel;
   
   class Client extends BaseModel
   {
       use Authorisations;
   
       public function department(): BelongsTo
       {
           return $this->belongsTo(Department::class);
       }
   }
   ```

3. 在 `config/module.php` 中的 `Permission => data_scope => resources` 中定义 `API` 权限。后台的权限管理页面将根据该配置进行展示。

   ```php
   "resources" => [
        ['displayName' => '委托单位数据', 'name' => 'client', 'model' => \App\Models\Client::class],
   ]
    ```
4. 在 `Controller` 的构造函数中添加中间件

   ```php
   // App\Http\Kernel.php `$middlewareAliases` 中添加中间件别名
   'dataScope.setup' => \Modules\Permission\Http\Middleware\DataScopeSetup::class,
   ```

   ```php
   $this->middleware('dataScope.setup:' . 'the_role_key');
   ```

5. 在逻辑中使用控制器中使用 `authorise()` Scope 进行数据权限控制。

   ```php
    public function items(Request $request)
    {
        $pagination = Client::authorise()->orderBy('id', 'desc')->paginate();
        return $this->json($pagination);
    }
   ```

## 模块代码

### 命令

```bash
php artisan permission:sync   #同步权限到数据库
```

### 数据表

```bash
2023_02_27_141644_create_permission_tables.php    # 由 `laravel-permission` 创建提供 RBAC 功能
2023_03_09_000000_create_data_scopes_table.php    # 数据权限表
```

### Seeder

```bash
Modules\Permission\Database\Seeders\PermissionSeeder    # 初始化权限
```

### 数据模型/Scope

```bash
Modules\Permission\Entities\Permission                # 权限 Model                      
Modules\Permission\Entities\DataScope                 # 数据范围 Model
Modules\Permission\Entities\Role                      # 角色 Model
Modules\Permission\Entities\Scope\AuthorisationScope  # 数据权限 Scope
```

### 枚举

```php
enum Scope: int
{

    case NONE = 0;                           //不可见
    case SELF = 1;                           //本人创建
    case DEPARTMENT = 2;                     //本部门
    case DEPARTMENT_AND_SUBORDINATE = 4;     //本部门及子部门
    case ALL = 8;                            //全部数据
}
```

### 辅助函数

`permission_get_page_permissions`

```php
/**
  * 获取页面的权限
  * @param string $page 页面名称
  * @param Collection $permissions 用户的权限集合
  * @return array
  */
function permission_get_page_permissions(string $page, Collection $permissions): array
```

### Controller

```bash
Modules\Permission\Http\Controllers\RoleController       #提供角色以及数据权限的CRUD
```

### 中间件

```bash
Modules\Permission\Http\Middleware\DataScopeSetup        #数据权限中间件，通常在管理端的父路由中使用
```

### UI

#### PC 端页面

```bash
web/pageRole.vue           #提供角色信息及权限管理页面
```

### Service

+ **`PermissionService`**

    - `syncRolePermissions` 同步角色权限

       ```php
      /**
         * 同步角色权限
         * @param Role $role
         * @param array $permissions
         * @return array
         */
        public function syncRolePermissions(Role $role, array $permissions): array
       ```
    - `getAllPermissions` 获取所有权限

       ```php
      /**
         * 获取所有权限
         * @return Collection
         */
        public function getAllPermissions(): Collection
       ```