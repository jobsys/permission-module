<template>
    <NewbieTable
        ref="list"
        :columns="columns()"
        :pagination="false"
        :filterable="false"
        v-auth="'api.manager.permission.role.items'"
        :after-fetched="(res) => ({ items: res.result })"
        :url="route('api.manager.permission.role.items')"
    >
        <template #functional>
            <NewbieButton type="primary" :icon="h(PlusOutlined)" @click="onEdit(false)" v-auth="'api.manager.permission.role.edit'"
            >新增角色
            </NewbieButton>
        </template>
    </NewbieTable>
    <NewbieModal v-model:visible="state.showRoleEditor" title="角色详情" :width="800" @close="closeEditor(false)">
        <a-tabs v-model:activeKey="state.activeTab">
            <a-tab-pane key="role" tab="角色信息" v-auth="'api.manager.permission.role.edit'">
                <NewbieForm
                    ref="edit"
                    :submit-url="route('api.manager.permission.role.edit')"
                    :card-wrapper="false"
                    :data="state.info"
                    :form="getForm()"
                    :close="closeEditor"
                    @success="onSubmitRole"
                />
            </a-tab-pane>
            <a-tab-pane key="permission" tab="角色权限" v-auth="'api.manager.permission.role.permission.items'">
                <a-alert
                    message="权限对应管理系统菜单项，如果某菜单下的功能全未被选中，则该菜单项对该角色不可见"
                    type="info"
                    show-icon
                    class="!mb-6"
                />
                <a-spin :spinning="state.permissionFetcher.loading">
                    <div class="!bg-gray-100 !p-6 max-h-[300px] overflow-y-auto">
                        <a-tree
                            v-model:expandedKeys="state.permissions.expandedKeys"
                            v-model:checkedKeys="state.permissions.checkedKeys"
                            checkable
                            :selectable="false"
                            :field-names="{ title: 'displayName' }"
                            :tree-data="state.permissions.data"
                            class="!bg-gray-100"
                        />
                    </div>

                    <a-row class="mt-4">
                        <a-col :offset="6">
                            <a-button
                                type="primary"
                                :loading="state.permissionSubmitter.loading"
                                @click="onSubmitPermission"
                                v-auth="'api.manager.permission.role.permission.edit'"
                            >
                                保存
                            </a-button>
                            <a-button class="ml-2" @click="closeEditor(false)">关闭</a-button>
                        </a-col>
                    </a-row>
                </a-spin>
            </a-tab-pane>
            <a-tab-pane key="dataScope" tab="数据权限" v-if="$auth('api.manager.permission.role.data-scope.items')">
                <a-alert
                    message="未设置数据范围的数据类型均按 【默认】的数据范围处理；在添加或者删除了数据类型后请记得保存"
                    type="info"
                    show-icon
                    class="!mb-6"
                />
                <a-spin :spinning="state.scopeFetcher.loading">
                    <a-table
                        :columns="scopeColumns()"
                        :data-source="state.scopes.selectedScopes"
                        size="middle"
                        :pagination="false"
                        :scroll="{ y: 300 }"
                    >
                        <template #bodyCell="{ column, record }">
                            <template v-if="column.dataIndex === 'name'">
                                <span style="font-weight: bold">{{ record.displayName }}</span>
                            </template>

                            <template v-if="column.dataIndex === 'scope'">
                                <a-select key="scope" v-model:value="record.value" :options="record.options" style="width: 200px"></a-select>
                            </template>
                        </template>
                    </a-table>

                    <template v-if="remainDataScopes.length && $auth('api.manager.permission.role.data-scope.edit')">
                        <a-button type="primary" class="mt-4" @click="onPrepareAddScope">
                            <template #icon>
                                <PlusOutlined></PlusOutlined>
                            </template>
                            添加数据类型
                        </a-button>
                        <a-divider></a-divider>
                    </template>
                    <a-row class="mt-4">
                        <a-col :offset="6">
                            <a-button
                                type="primary"
                                :loading="state.scopeSubmitter.loading"
                                @click="onSubmitScope"
                                v-auth="'api.manager.permission.role.permission.edit'"
                            >
                                保存
                            </a-button>
                            <a-button class="ml-2" @click="closeEditor(false)">关闭</a-button>
                        </a-col>
                    </a-row>
                </a-spin>
            </a-tab-pane>
        </a-tabs>
    </NewbieModal>

    <a-modal v-model:open="state.showAddScopeModal" @ok="onAddScope">
        <a-form>
            <a-form-item label="数据类型">
                <a-select v-model:value="state.scopeAddForm.name" style="width: 200px">
                    <a-select-option v-for="option in remainDataScopes" :key="option.name" :value="option.name">
                        {{ option.displayName }}
                    </a-select-option>
                </a-select>
            </a-form-item>

            <a-form-item label="数据范围">
                <a-select v-model:value="state.scopeAddForm.value" :options="addScopeOptions" style="width: 200px"></a-select>
            </a-form-item>
        </a-form>
    </a-modal>
</template>

<script setup>
import { useTableActions } from "jobsys-newbie"
import { useFetch, useModalConfirm, useProcessStatusSuccess } from "jobsys-newbie/hooks"
import { DeleteOutlined, EditOutlined, PlusOutlined } from "@ant-design/icons-vue"
import { message } from "ant-design-vue"
import { computed, h, inject, reactive, ref, watch } from "vue"
import { find, map, sortBy } from "lodash-es"

const props = defineProps({
    superRole: {
        type: String,
        default: "super-admin",
    },
})

const list = ref(null)

const route = inject("route")
const auth = inject("auth")

const state = reactive({
    showRoleEditor: false,
    showAddScopeModal: false,
    info: {},
    activeTab: "role",
    permissions: {
        expandedKeys: [],
        checkedKeys: [],
        data: [],
    },
    scopeAddForm: {
        name: "",
        value: "",
    },
    scopes: {
        totalScopes: [],
        selectedScopes: [],
    },
    permissionFetcher: {},
    permissionSubmitter: {},
    scopeFetcher: {},
    scopeSubmitter: {},
})

const remainDataScopes = computed(() => {
    return state.scopes.totalScopes.filter((scope) => {
        return find(state.scopes.selectedScopes, { name: scope.name }) === undefined
    })
})

const addScopeOptions = computed(() => {
    if (!state.scopeAddForm.name) {
        return []
    }
    return find(remainDataScopes.value, { name: state.scopeAddForm.name }).options
})

const closeEditor = (isRefresh) => {
    if (isRefresh) {
        list.value.doFetch()
    }
    state.showRoleEditor = false
    state.activeTab = "role"
    state.permissions.data = []
    state.scopes.totalScopes = []
}

const fetchPermissions = async (roleId) => {
    const res = await useFetch(state.permissionFetcher).get(route("api.manager.permission.role.permission.items", { id: roleId }))
    useProcessStatusSuccess(res, () => {
        const { menus } = res.result
        state.permissions.data = menus
        state.permissions.expandedKeys = map(menus, "key")
        state.permissions.checkedKeys = res.result.role_permissions
    })
}

const fetchScopes = async (roleId) => {
    const res = await useFetch(state.scopeFetcher).get(route("api.manager.permission.role.data-scope.items", { id: roleId }))
    useProcessStatusSuccess(res, () => {
        state.scopes.totalScopes = res.result.scopes
        state.scopes.selectedScopes = sortBy(res.result.role_scopes, ({ name }) => (name === "default" ? 0 : 1))
    })
}

const onSubmitRole = (res) => {
    state.info = res.result
    if (auth("api.manager.permission.role.permission.edit")) {
        state.activeTab = "permission"
    } else {
        closeEditor(true)
    }
}

const onSubmitPermission = async () => {
    const res = await useFetch(state.permissionSubmitter).post(route("api.manager.permission.role.permission.edit"), {
        id: state.info.id,
        permissions: state.permissions.checkedKeys,
    })
    useProcessStatusSuccess(res, () => {
        message.success("保存成功")
    })
}

const onEdit = (item) => {
    state.info = item || {}
    state.showRoleEditor = true
}

const onPrepareAddScope = () => {
    if (!remainDataScopes.value || !remainDataScopes.value.length) {
        return
    }
    const defaultValue = find(state.scopes.selectedScopes, { name: "default" })?.value || 0

    state.scopeAddForm = { name: remainDataScopes.value[0].name, value: defaultValue }
    state.showAddScopeModal = true
}

const onAddScope = () => {
    const scope = find(remainDataScopes.value, { name: state.scopeAddForm.name })
    state.scopes.selectedScopes.push({ ...scope, value: state.scopeAddForm.value })
    state.scopeAddForm = { name: "", value: "" }
    state.showAddScopeModal = false
}

const onDeleteScope = (index) => {
    state.scopes.selectedScopes.splice(index, 1)
}

const onSubmitScope = async () => {
    const scope = {}
    state.scopes.selectedScopes.forEach((item) => {
        scope[item.name] = item.value
    })
    const res = await useFetch(state.scopeSubmitter).post(route("api.manager.permission.role.data-scope.edit"), {
        id: state.info.id,
        scope,
    })
    useProcessStatusSuccess(res, () => {
        message.success("保存成功")
    })
}

const onDelete = (item) => {
    const modal = useModalConfirm(
        `您确认要删除 ${item.name} 吗？`,
        async () => {
            try {
                const res = await useFetch().post(route("api.manager.permission.role.delete", { id: item.id }))
                modal.destroy()
                useProcessStatusSuccess(res, () => {
                    message.success("删除成功")
                    list.value.doFetch()
                })
            } catch (e) {
                modal.destroy()
            }
        },
        true,
    )
}

watch(
    () => state.activeTab,
    (tab) => {
        if (tab === "permission") {
            if (!state.info || !state.info.id) {
                message.error("请先保存角色信息")
                state.activeTab = "role"
            } else if (!state.permissions.data || !state.permissions.data.length) {
                fetchPermissions(state.info.id)
            }
        } else if (tab === "dataScope") {
            if (!state.info || !state.info.id) {
                message.error("请先保存角色信息")
                state.activeTab = "role"
            } else if (!state.scopes.totalScopes || !state.scopes.totalScopes.length) {
                fetchScopes(state.info.id)
            }
        }
    },
)

const columns = () => {
    return [
        {
            title: "角色名称",
            width: 200,
            dataIndex: "display_name",
        },
        {
            title: "角色标识",
            width: 200,
            dataIndex: "name",
        },
        {
            title: "描述",
            dataIndex: "description",
            width: 200,
        },
        {
            title: "激活状态",
            key: "is_active",
            width: 80,
            align: "center",
            customRender({ record }) {
                return useTableActions({
                    type: "tag",
                    name: record.is_active ? "激活" : "禁用",
                    props: { color: record.is_active ? "green" : "red" },
                })
            },
        },
        {
            title: "操作",
            width: 160,
            key: "operation",
            fixed: "right",
            customRender({ record }) {
                if (record.name === props.superRole) {
                    return useTableActions([])
                }

                const actions = []

                if (auth("api.manager.permission.role.edit")) {
                    actions.push({
                        name: "编辑",
                        props: {
                            icon: h(EditOutlined),
                            size: "small",
                        },
                        action() {
                            onEdit(record)
                        },
                    })
                }

                if (auth("api.manager.permission.role.delete")) {
                    actions.push({
                        name: "删除",
                        props: {
                            icon: h(DeleteOutlined),
                            size: "small",
                        },
                        action() {
                            onDelete(record)
                        },
                    })
                }

                return useTableActions(actions)
            },
        },
    ]
}

const scopeColumns = () => {
    return [
        {
            title: "数据类型",
            dataIndex: "name",
            width: 200,
        },
        {
            title: "数据范围",
            dataIndex: "scope",
            width: 200,
        },
        {
            title: "操作",
            width: 160,
            key: "operation",
            fixed: "right",
            customRender({ record, index }) {
                if (record.name !== "default") {
                    return useTableActions([
                        {
                            name: "删除",
                            props: {
                                icon: "DeleteOutlined",
                                size: "small",
                                // auth: 'api.manager.permission.role.delete'
                            },
                            action() {
                                onDeleteScope(index)
                            },
                        },
                    ])
                }
                return null
            },
        },
    ]
}

const getForm = () => {
    return [
        {
            key: "display_name",
            title: "角色名称",
            tips: "必需唯一",
            required: true,
        },
        {
            key: "name",
            title: "角色标识",
            help: "必需唯一，用于系统区分，推荐使用字母，数字，中划线组合",
            required: true,
        },
        {
            key: "description",
            title: "描述",
            type: "textarea",
        },
        {
            key: "is_active",
            title: "激活状态",
            type: "switch",
            options: ["激活", "禁用"],
            help: "未激活状态的角色将无法使用",
            defaultValue: true,
            position: "right",
        },
    ]
}
</script>
