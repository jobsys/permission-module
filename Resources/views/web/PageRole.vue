<template>
	<NewbieTable
		ref="list"
		:columns="columns()"
		:pagination="false"
		:filterable="false"
		v-if="auth('api.manager.permission.role.items')"
		:after-fetched="(res) => ({ items: res.result })"
		:url="route('api.manager.permission.role.items')"
	>
		<template #functional>
			<NewbieButton type="primary" :icon="h(PlusOutlined)" @click="onEdit(false)" v-if="auth('api.manager.permission.role.edit')"
				>新增角色
			</NewbieButton>
		</template>
	</NewbieTable>
	<NewbieModal v-model:visible="state.showRoleEditor" title="角色详情" :width="800" @close="closeEditor(false)">
		<NewbieForm
			ref="edit"
			:submit-url="route('api.manager.permission.role.edit')"
			:card-wrapper="false"
			:data="state.info"
			:form="getForm()"
			:close="closeEditor"
			@success="onSubmitRole"
		/>
	</NewbieModal>
	<PermissionAuthorization mode="role" :info="state.info" ref="permissionAuthorizationRef"></PermissionAuthorization>
</template>

<script setup>
import { useTableActions } from "jobsys-newbie"
import { useFetch, useModalConfirm, useProcessStatusSuccess } from "jobsys-newbie/hooks"
import { AuditOutlined, DeleteOutlined, EditOutlined, PlusOutlined } from "@ant-design/icons-vue"
import { message } from "ant-design-vue"
import { h, inject, nextTick, reactive, ref } from "vue"
import PermissionAuthorization from "@modules/Permission/Resources/views/web/components/PermissionAuthorization.vue"

const props = defineProps({
	superRole: {
		type: String,
		default: "super-admin",
	},
})

const list = ref()
const permissionAuthorizationRef = ref()

const route = inject("route")
const auth = inject("auth")

const state = reactive({
	showRoleEditor: false,
	info: {},
})

const closeEditor = (isRefresh) => {
	if (isRefresh) {
		list.value?.doFetch()
	}
	state.showRoleEditor = false
}

const onSubmitRole = () => {
	closeEditor(true)
	list.value?.doFetch()
}

const onEdit = (item) => {
	state.info = item || {}
	state.showRoleEditor = true
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

				actions.push({
					name: "角色权限",
					props: {
						icon: h(AuditOutlined),
						size: "small",
					},
					action() {
						state.info = record
						nextTick(() => {
							permissionAuthorizationRef.value?.open()
						})
					},
				})

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
