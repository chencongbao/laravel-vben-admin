<script setup lang="ts">
import type { AdminSession } from '#/api';
import { onMounted, ref } from 'vue';
import { Button, Card, message, Popconfirm, Space, Table, Tag } from 'ant-design-vue';
import { getSessionsApi, revokeOtherSessionsApi, revokeSessionApi } from '#/api';

const loading = ref(false); const sessions = ref<AdminSession[]>([]);
const columns = [
  { dataIndex: 'name', title: '令牌名称' }, { dataIndex: 'created_at', title: '登录时间' },
  { dataIndex: 'last_used_at', title: '最后使用' }, { dataIndex: 'current', title: '会话' },
  { dataIndex: 'action', title: '操作' },
];
async function load() {
  loading.value = true;
  try { sessions.value = (await getSessionsApi()).sessions; } finally { loading.value = false; }
}
async function revoke(session: AdminSession) { await revokeSessionApi(session.id); message.success('会话已撤销'); await load(); }
async function revokeOthers() {
  const result = await revokeOtherSessionsApi();
  message.success(`已撤销 ${result.revoked_count} 个其他会话`); await load();
}
onMounted(load);
</script>

<template>
  <div class="space-y-4">
    <Card title="登录会话">
      <template #extra>
        <Popconfirm title="确定让其他设备全部退出登录？" @confirm="revokeOthers"><Button danger>下线其他设备</Button></Popconfirm>
      </template>
      <Table :columns="columns" :data-source="sessions" :loading="loading" :pagination="false" row-key="id">
        <template #bodyCell="{ column, record, text }">
          <Tag v-if="column.dataIndex === 'current'" :color="text ? 'green' : 'default'">{{ text ? '当前会话' : '其他会话' }}</Tag>
          <span v-else-if="column.dataIndex === 'last_used_at'">{{ text || '尚无记录' }}</span>
          <Space v-else-if="column.dataIndex === 'action'">
            <Tag v-if="record.current" color="green">受保护</Tag>
            <Popconfirm v-else title="确定撤销此登录会话？" @confirm="revoke(record)"><Button danger size="small" type="link">撤销</Button></Popconfirm>
          </Space>
        </template>
      </Table>
    </Card>
    <Card title="安全能力说明">
      <p>修改密码需要验证当前密码；成功后会撤销除当前会话外的其他令牌。</p>
      <p class="mt-2 text-muted-foreground">MFA 尚未在基础包中实现，后续需要单独设计密钥加密、恢复码和强制策略。</p>
    </Card>
  </div>
</template>
