<template>
	<a-modal v-model:open="state.showAuthorizationModal" :destroy-on-close="true" :width="840" :footer="null" title="权限管理">
		<a-tabs v-model:activeKey="state.activeTab">
			<a-tab-pane
				key="permission"
				:tab="mode === 'role' ? '角色操作权限' : '自定义操作权限'"
				v-if="auth('api.manager.permission.role.permission.items')"
			>
				<a-alert type="success" v-if="mode === 'role'" :message="`当前操作角色: ${info.display_name}`"></a-alert>
				<a-alert type="info" show-icon class="my-4">
					<template #message>
						<div>1. 权限对应管理系统菜单项，如果某菜单下的功能全未被选中，则该菜单项对该角色/用户不可见</div>
						<div>2. 如果用户有自定义权限，将以用户自定义权限为准</div>
					</template>
				</a-alert>
				<template v-if="mode === 'user'">
					<a-alert type="warning" show-icon class="my-4">
						<template #message>
							<div class="flex items-center justify-between">
								<div>如果设置了用户权限，那角色权限将对该用户无效，权限以用户权限为准</div>

								<a-tooltip title="清除用户自定义权限后将以用户角色权限为准">
									<NewbieButton
										type="primary"
										:icon="h(ClearOutlined)"
										:fetcher="state.permissionClearFetcher"
										@click="onClearCustomPermission"
										>清除用户自定义权限</NewbieButton
									>
								</a-tooltip>
							</div>
						</template>
					</a-alert>
				</template>

				<a-spin :spinning="state.permissionFetcher.loading">
					<div class="!bg-gray-100 !p-6 max-h-[300px] overflow-y-auto">
						<a-tree
							v-model:expandedKeys="state.permissions.expandedKeys"
							v-model:checkedKeys="state.permissions.checkedKeys"
							checkable
							:selectable="false"
							:field-names="{ title: 'displayName', key: 'key', children: 'children' }"
							:tree-data="state.permissions.data"
							class="!bg-gray-100"
						/>
					</div>

					<div class="flex justify-center items-center mt-4">
						<a-button
							v-if="auth('api.manager.permission.role.permission.edit')"
							type="primary"
							:loading="state.permissionSubmitter.loading"
							@click="onSubmitPermission"
						>
							保存
						</a-button>
						<a-button class="ml-2" @click="closeEditor()">关闭</a-button>
					</div>
				</a-spin>
			</a-tab-pane>
			<a-tab-pane
				key="dataScope"
				:tab="mode === 'role' ? '角色数据权限' : '自定义数据权限'"
				v-if="auth('api.manager.permission.role.data-scope.items')"
			>
				<a-alert type="warning" show-icon v-if="mode === 'user'" class="my-4">
					<template #message>
						<div class="flex items-center justify-between">
							<div>如果设置了用户数据权限，那角色数据权限将对该用户无效，数据权限以用户数据权限为准</div>

							<a-tooltip title="清除用户自定义数据权限后将以用户角色权限为准">
								<NewbieButton
									type="primary"
									:icon="h(ClearOutlined)"
									:fetcher="state.dataScopeClearFetcher"
									@click="onClearCustomDataScope"
									>清除自定义数据权限</NewbieButton
								>
							</a-tooltip>
						</div>
					</template>
				</a-alert>
				<a-alert type="success" v-if="state.mode === 'role'" :message="`当前操作角色: ${info.display_name}`"></a-alert>
				<a-alert
					v-if="mode === 'role'"
					message="未设置数据范围的数据类型均按 【默认】的数据范围处理；在添加或者删除了数据类型后请记得保存"
					type="info"
					show-icon
					class="my-4"
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

					<template v-if="remainDataScopes.length && auth('api.manager.permission.role.data-scope.edit')">
						<div class="flex justify-start">
							<a-button type="primary" class="mt-4" @click="onPrepareAddScope">
								<template #icon>
									<PlusOutlined></PlusOutlined>
								</template>
								添加数据类型
							</a-button>
						</div>
						<a-divider></a-divider>
					</template>
					<div class="flex justify-center items-center mt-4">
						<a-button
							v-if="auth('api.manager.permission.role.permission.edit')"
							type="primary"
							:loading="state.scopeSubmitter.loading"
							@click="onSubmitScope"
						>
							保存
						</a-button>
						<a-button class="ml-2" @click="closeEditor()">关闭</a-button>
					</div>
				</a-spin>
			</a-tab-pane>
		</a-tabs>
	</a-modal>

	<a-modal
		v-model:open="state.showAddScopeModal"
		:destroy-on-close="true"
		@ok="onAddScope"
		title="数据权限定义"
		@cancel="() => (state.isModifyCustom = false)"
		:width="700"
	>
		<a-form class="mt-6">
			<a-form-item label="数据类型" required>
				<a-select
					v-model:value="state.scopeAddForm.name"
					style="width: 200px"
					@change="
						() => {
							state.scopeAddForm.value = undefined
						}
					"
				>
					<a-select-option v-for="option in remainDataScopes" :key="option.name" :value="option.name">
						{{ option.displayName }}
					</a-select-option>
				</a-select>
			</a-form-item>

			<a-form-item label="数据范围" help="不同的数据类型的可选数据范围不尽相同，由相关业务决定" required>
				<a-select
					v-model:value="state.scopeAddForm.value"
					:options="addScopeOptions"
					@change="onSelectScopeOptions"
					placeholder="请选择数据范围"
					style="width: 200px"
				></a-select>
			</a-form-item>

			<a-form-item label="自定义数据" v-if="state.scopeAddForm.value === -1" help="对于未选择数据范围的数据类型不作限制">
				<a-table :columns="customColumns()" :data-source="state.customOptions" size="middle" :pagination="false" :scroll="{ y: 300 }">
					<template #bodyCell="{ column, record }">
						<template v-if="column.dataIndex === 'label'">
							<span>{{ record.label }}</span>
						</template>

						<template v-if="column.dataIndex === 'scope'">
							<a-select
								:placeholder="`请选择${record.label}`"
								key="scope"
								mode="multiple"
								allow-clear
								show-search
								v-model:value="state.scopeAddForm.custom[record.field]"
								:options="record.propOptions"
								style="width: 200px"
							></a-select>
						</template>
					</template>
				</a-table>
			</a-form-item>
		</a-form>
	</a-modal>
</template>
<script setup>
import { computed, h, inject, nextTick, reactive, watch } from "vue"
import { ClearOutlined, DeleteOutlined, EditOutlined, PlusOutlined } from "@ant-design/icons-vue"
import { useFetch, useModalConfirm, useProcessStatusSuccess } from "jobsys-newbie/hooks"
import { message } from "ant-design-vue"
import { useTableActions } from "jobsys-newbie"
import { find, isArray, map, sortBy } from "lodash-es"

const props = defineProps({
	mode: { type: String, default: "role" },
	info: {
		type: Object,
		default: () => {},
	},
})

const emits = defineEmits(["close"])

const auth = inject("auth")
const route = inject("route")

const state = reactive({
	showAuthorizationModal: false,
	activeTab: "permission",
	permissionFetcher: {},
	permissionSubmitter: {},
	permissionClearFetcher: {},
	permissions: {
		expandedKeys: [],
		checkedKeys: [],
		data: [],
	},
	scopeAddForm: {
		name: "",
		value: "",
		custom: {},
	},
	scopes: {
		totalScopes: [],
		selectedScopes: [],
	},
	customOptions: [],

	isModifyCustom: false,
	showAddScopeModal: false,
	scopeFetcher: {},
	scopeSubmitter: {},
})

//过滤出未被选中的数据类型
const remainDataScopes = computed(() => {
	//如果是修改自定义数据时只需要返回当前修改的数据类型
	if (state.isModifyCustom) {
		return [find(state.scopes.selectedScopes, { name: state.scopeAddForm.name })]
	}

	return state.scopes.totalScopes.filter((scope) => {
		return find(state.scopes.selectedScopes, { name: scope.name }) === undefined
	})
})

//从未被选中的数据类型中获取选项
const addScopeOptions = computed(() => {
	//当是修改自定义数据时只需要返回当前修改的数据类型的选项
	if (state.isModifyCustom) {
		return find(state.scopes.selectedScopes, { name: state.scopeAddForm.name })?.options || []
	}

	if (!state.scopeAddForm.name || !remainDataScopes.value?.length) {
		return []
	}

	return find(remainDataScopes.value, { name: state.scopeAddForm.name }).options
})

const fetchPermissions = async (id) => {
	const res = await useFetch(state.permissionFetcher).get(
		route("api.manager.permission.role.permission.items", {
			id,
			mode: props.mode,
		}),
	)
	useProcessStatusSuccess(res, () => {
		const { menus } = res.result
		state.permissions.data = menus
		nextTick(() => {
			state.permissions.expandedKeys = map(menus, "key")
			state.permissions.checkedKeys = res.result.auth_permissions
		})
	})
}

const fetchScopes = async (id) => {
	const res = await useFetch(state.scopeFetcher).get(
		route("api.manager.permission.role.data-scope.items", {
			id,
			mode: props.mode,
		}),
	)
	useProcessStatusSuccess(res, () => {
		state.scopes.totalScopes = res.result.scopes
		state.scopes.selectedScopes = sortBy(
			res.result.role_scopes.map((item) => {
				if (isArray(item.custom)) {
					return { ...item, custom: {} }
				}
				return item
			}),
			({ name }) => (name === "default" ? 0 : 1),
		)
	})
}

const open = () => {
	state.activeTab = "permission"
	fetchPermissions(props.info?.id)
	state.showAuthorizationModal = true
}

defineExpose({ open })

watch(
	() => state.activeTab,
	(tab) => {
		if (tab === "permission") {
			fetchPermissions(props.info.id)
		} else if (tab === "dataScope") {
			fetchScopes(props.info.id)
		}
	},
)

const closeEditor = () => {
	state.permissions.data = []
	state.scopes.totalScopes = []
	state.showAuthorizationModal = false
	emits("close")
}

const onClearCustomPermission = () => {
	const modal = useModalConfirm(
		`您确认要清除当前用户自定义权限吗？清除用户自定义权限后将以用户角色权限为准`,
		async () => {
			try {
				const res = await useFetch(state.permissionClearFetcher).post(route("api.manager.permission.user.permission.clear"), {
					id: props.info.id,
				})
				modal.destroy()
				useProcessStatusSuccess(res, () => {
					message.success("清除成功，用户恢复角色权限")
					closeEditor()
				})
			} catch (e) {
				modal.destroy(e)
			}
		},
		true,
	)
}

const onClearCustomDataScope = () => {
	const modal = useModalConfirm(
		`您确认要清除当前用户自定义数据权限吗？清除用户自定义数据权限后将以用户角色数据权限为准`,
		async () => {
			try {
				const res = await useFetch(state.permissionClearFetcher).post(route("api.manager.permission.user.data-scope.clear"), {
					id: props.info.id,
				})
				modal.destroy()
				useProcessStatusSuccess(res, () => {
					message.success("清除成功，用户恢复角色数据权限")
					closeEditor()
				})
			} catch (e) {
				modal.destroy(e)
			}
		},
		true,
	)
}

const onAddScope = () => {
	if (!state.scopeAddForm.name) {
		message.warn("请选择数据类型")
		return
	}

	if (!state.scopeAddForm.value && state.scopeAddForm.value !== 0) {
		message.warn("请选择数据范围")
		return
	}

	const scope = find(remainDataScopes.value, { name: state.scopeAddForm.name })
	if (state.isModifyCustom) {
		state.scopes.selectedScopes = state.scopes.selectedScopes.map((item) => {
			if (item.name === state.scopeAddForm.name) {
				return { ...scope, value: state.scopeAddForm.value, custom: state.scopeAddForm.custom }
			}
			return item
		})
		state.isModifyCustom = false
	} else {
		state.scopes.selectedScopes.push({ ...scope, value: state.scopeAddForm.value, custom: state.scopeAddForm.custom })
	}

	state.scopeAddForm = { name: "", value: "", custom: {} }
	state.showAddScopeModal = false
}

const onPrepareAddScope = () => {
	if (!remainDataScopes.value || !remainDataScopes.value.length) {
		return
	}

	state.scopeAddForm = { name: remainDataScopes.value[0].name, value: undefined, custom: {} }
	state.showAddScopeModal = true
}

const onSubmitScope = async () => {
	const scope = {}
	state.scopes.selectedScopes.forEach((item) => {
		if (item.value === -1) {
			//自定义数据类型
			scope[item.name] = item.custom
		} else {
			//非自定义该数数据类型为范围数值
			scope[item.name] = item.value
		}
	})
	const res = await useFetch(state.scopeSubmitter).post(route("api.manager.permission.role.data-scope.edit", { mode: props.mode }), {
		id: props.info.id,
		scope,
	})
	useProcessStatusSuccess(res, () => {
		message.success("保存成功")
	})
}

const onSubmitPermission = async () => {
	const res = await useFetch(state.permissionSubmitter).post(route("api.manager.permission.role.permission.edit", { mode: props.mode }), {
		id: props.info.id,
		permissions: state.permissions.checkedKeys,
	})
	useProcessStatusSuccess(res, () => {
		message.success("保存成功")
	})
}

const onSelectScopeOptions = (value, option) => {
	//如果是自定义
	if (value === -1 && option.customOptions?.length) {
		state.customOptions = option.customOptions

		const customInit = {}

		option.customOptions.forEach((op) => {
			customInit[op.field] = undefined
		})

		state.scopeAddForm.custom = customInit
	} else {
		state.customOptions = []
	}
}

const onEditCustomScope = (record) => {
	state.isModifyCustom = true
	state.scopeAddForm = { name: record.name, value: record.value, custom: record.custom || {} }
	state.customOptions = find(record.options, { value: -1 })?.customOptions || []
	state.showAddScopeModal = true
}

const onDeleteScope = (index) => {
	state.scopes.selectedScopes.splice(index, 1)
}

const customColumns = () => {
	return [
		{
			title: "自定义数据类型",
			dataIndex: "label",
			width: 200,
		},
		{
			title: "自定义数据范围",
			dataIndex: "scope",
			width: 300,
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
				if ((props.mode === "role" && record.name !== "default") || props.mode === "user") {
					const actions = [
						{
							name: "删除",
							props: {
								icon: h(DeleteOutlined),
								size: "small",
								// auth: 'api.manager.permission.role.delete'
							},
							action() {
								onDeleteScope(index)
							},
						},
					]

					if (record.value === -1) {
						actions.push({
							name: "编辑范围",
							props: {
								icon: h(EditOutlined),
								size: "small",
							},
							action() {
								onEditCustomScope(record)
							},
						})
					}

					return useTableActions(actions)
				}
				return null
			},
		},
	]
}
</script>
