<template>
  <div class="summary-page">
    <header class="page-header">
      <h1 class="page-title">汇总看板</h1>
      <p class="page-desc">问题图与整改图成对展示，按 #key 从小到大排序，同一徽章（检查项+分值）共用</p>
    </header>

    <section v-loading="loading" class="summary-section">
      <!-- 日期筛选：变更后统计实时刷新 -->
      <div class="card filter-card">
        <div class="filter-left">
          <span class="card-icon small">
            <el-icon><Calendar /></el-icon>
          </span>
          <div class="filter-text">
            <h2 class="card-title">检查日期筛选</h2>
            <p class="card-sub">按检查日期范围统计，完成率随实时数据计算</p>
          </div>
        </div>
        <div class="filter-actions">
          <el-date-picker
            v-model="dateRange"
            type="daterange"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            value-format="YYYY-MM-DD"
            :shortcuts="dateShortcuts"
            class="date-range-picker"
          />
          <el-button @click="dateRange = null">重置</el-button>
        </div>
      </div>

      <!-- 顶部总览：整改完成率 + 汇总指标（全部由当前数据实时计算） -->
      <div class="overview-grid">
        <div class="card overview-card rate-card">
          <div class="overview-head">
            <span class="overview-label">整改完成率</span>
            <el-tag v-if="totals.total > 0 && completionRate >= 100" type="success" size="small">全部完成</el-tag>
          </div>
          <div class="rate-main">
            <span class="rate-value" :class="completionRate >= 100 ? 'success' : 'primary'">{{ completionRate }}%</span>
          </div>
          <el-progress
            :percentage="completionRate"
            :stroke-width="10"
            :show-text="false"
            :status="completionRate >= 100 ? 'success' : ''"
          />
          <p class="rate-detail">已整改 {{ totals.completed }} / 共 {{ totals.total }} 个问题，未整改 {{ totals.pending }} 个</p>
        </div>

        <div class="card overview-card">
          <span class="overview-label">问题总数</span>
          <span class="overview-value primary">{{ totals.total }}</span>
          <span class="overview-foot">当前筛选范围</span>
        </div>

        <div class="card overview-card">
          <span class="overview-label">已整改</span>
          <span class="overview-value success">{{ totals.completed }}</span>
          <span class="overview-foot">已上传整改图</span>
        </div>

        <div class="card overview-card">
          <span class="overview-label">未整改</span>
          <span class="overview-value" :class="totals.pending > 0 ? 'warning' : 'muted'">{{ totals.pending }}</span>
          <span class="overview-foot">待处理问题</span>
        </div>

        <div class="card overview-card">
          <span class="overview-label">总扣分</span>
          <span class="overview-value danger">-{{ totals.totalScore }}分</span>
          <span class="overview-foot">问题扣分合计</span>
        </div>
      </div>

      <!-- 按员工统计：点击员工跳到对应图片对 -->
      <div v-if="totals.total > 0" class="card table-card">
        <div class="card-header">
          <span class="card-icon small">
            <el-icon><User /></el-icon>
          </span>
          <h2 class="card-title">员工整改统计</h2>
          <span class="card-hint-inline">点击有问题记录的员工，可跳转到对应图片对</span>
        </div>
        <el-table
          :data="summary"
          stripe
          class="employee-table"
          :row-class-name="rowClassName"
          @row-click="jumpToEmployee"
        >
          <el-table-column label="员工" min-width="180">
            <template #default="{ row }">
              <span class="employee-cell" :class="{ link: row.total > 0 }">
                <span class="employee-avatar">{{ (row.user?.name || '员')[0] }}</span>
                <span class="employee-name">{{ row.user?.name || '未知员工' }}</span>
                <el-icon v-if="row.total > 0" class="jump-icon"><Top /></el-icon>
              </span>
            </template>
          </el-table-column>
          <el-table-column label="问题总数" prop="total" align="center" min-width="100" />
          <el-table-column label="已整改" align="center" min-width="100">
            <template #default="{ row }">
              <span class="num-success">{{ row.completed }}</span>
            </template>
          </el-table-column>
          <el-table-column label="未整改" align="center" min-width="100">
            <template #default="{ row }">
              <span :class="row.pending > 0 ? 'num-warning' : 'num-muted'">{{ row.pending }}</span>
            </template>
          </el-table-column>
          <el-table-column label="总扣分" align="center" min-width="100">
            <template #default="{ row }">
              <span class="num-danger">-{{ row.total_score }}分</span>
            </template>
          </el-table-column>
          <el-table-column label="完成率" min-width="200">
            <template #default="{ row }">
              <div class="rate-cell">
                <el-progress
                  :percentage="completionOf(row)"
                  :stroke-width="8"
                  :show-text="false"
                  :status="completionOf(row) >= 100 ? 'success' : ''"
                />
                <span class="rate-cell-text" :class="completionOf(row) >= 100 ? 'text-success' : 'text-primary'">
                  {{ completionOf(row) }}%
                </span>
              </div>
            </template>
          </el-table-column>
        </el-table>
      </div>

      <div v-if="totals.total === 0 && !loading" class="empty-state">
        <div class="empty-icon">
          <el-icon><DataAnalysis /></el-icon>
        </div>
        <p class="empty-text">当前筛选条件下暂无数据</p>
        <p class="empty-hint">请调整日期范围，或在检查上传中创建记录</p>
      </div>

      <!-- 图片对明细 -->
      <div
        v-for="item in visibleCards"
        :id="`employee-card-${item.user?.id}`"
        :key="item.user?.id"
        class="summary-card"
        :class="{ highlighted: highlightedId === item.user?.id }"
      >
        <div class="summary-header">
          <div class="summary-user">
            <div class="user-avatar">{{ (item.user?.name || '员')[0] }}</div>
            <h2 class="summary-name">{{ item.user?.name }}</h2>
            <el-tag size="small" type="info" effect="plain" class="date-tag">
              {{ activeDateText }}
            </el-tag>
          </div>
          <div class="summary-stats">
            <div class="stat">
              <span class="stat-label">整改进度</span>
              <span class="stat-value" :class="completionOf(item) >= 100 ? 'success' : 'primary'">
                {{ completionOf(item) }}%
              </span>
              <span class="stat-detail">（已 {{ item.completed }} / 共 {{ item.total }}，未整改 {{ item.pending }}）</span>
            </div>
            <div class="stat">
              <span class="stat-label">扣分合计</span>
              <span class="stat-value danger">-{{ item.total_score }}分</span>
            </div>
          </div>
        </div>
        <div class="summary-records">
          <div
            v-for="r in item.records"
            :key="r.id"
            class="record-item"
          >
            <div class="record-meta">
              <span class="record-seq">#{{ r.sequence_key }}</span>
              <span v-if="r.check_date" class="record-date">{{ r.check_date }}</span>
              <span class="badge-name">{{ r.item_name_snapshot || r.item?.name }}</span>
              <span class="badge-score">-{{ (r.item_score_snapshot ?? r.item?.score) }}分</span>
              <el-tag v-if="r.status === 'completed'" type="success" size="small" class="record-tag">已完成</el-tag>
              <el-tag v-else type="warning" size="small" class="record-tag">待整改</el-tag>
            </div>
            <p class="record-pair-desc">问题图 · 整改图（图片对）</p>
            <div class="record-images">
              <div class="record-img-wrap">
                <span class="img-label">问题</span>
                <img
                  :src="imageUrl(r.issue_image)"
                  alt="问题图"
                  @error="(e) => (e.target.style.display = 'none')"
                />
              </div>
              <div class="record-arrow">
                <el-icon v-if="r.status === 'completed'" class="text-success"><CircleCheck /></el-icon>
                <span v-else class="text-muted">→</span>
              </div>
              <div class="record-img-wrap">
                <span class="img-label">整改</span>
                <img
                  v-if="r.fix_image"
                  :src="imageUrl(r.fix_image)"
                  alt="整改图"
                  @error="(e) => (e.target.style.display = 'none')"
                />
                <div v-else class="record-placeholder">待处理</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { CircleCheck, DataAnalysis, Calendar, User, Top } from '@element-plus/icons-vue'
import { api, apiBase } from '@/api/request'

const loading = ref(true)
const summary = ref([])
const dateRange = ref(null)
const highlightedId = ref(null)
let highlightTimer = null

const dateShortcuts = [
  {
    text: '今天',
    value: () => {
      const d = new Date()
      return [d, d]
    },
  },
  {
    text: '最近7天',
    value: () => {
      const end = new Date()
      const start = new Date()
      start.setDate(start.getDate() - 6)
      return [start, end]
    },
  },
  {
    text: '最近30天',
    value: () => {
      const end = new Date()
      const start = new Date()
      start.setDate(start.getDate() - 29)
      return [start, end]
    },
  },
  {
    text: '本月',
    value: () => {
      const end = new Date()
      const start = new Date(end.getFullYear(), end.getMonth(), 1)
      return [start, end]
    },
  },
]

function imageUrl(path) {
  if (!path) return ''
  const base = apiBase() || (typeof window !== 'undefined' ? window.location.origin : '')
  return path.startsWith('http') ? path : (base.replace(/\/$/, '') + path)
}

// 汇总数据全部由当前接口返回实时聚合，不写死任何数值
const totals = computed(() =>
  summary.value.reduce(
    (acc, item) => {
      acc.total += item.total || 0
      acc.completed += item.completed || 0
      acc.pending += item.pending ?? Math.max((item.total || 0) - (item.completed || 0), 0)
      acc.totalScore += item.total_score || 0
      return acc
    },
    { total: 0, completed: 0, pending: 0, totalScore: 0 }
  )
)

function round1(n) {
  return Math.round(n * 10) / 10
}
function completionOf(row) {
  const total = row?.total || 0
  const completed = row?.completed || 0
  return total > 0 ? round1((completed / total) * 100) : 0
}
const completionRate = computed(() =>
  totals.value.total > 0 ? round1((totals.value.completed / totals.value.total) * 100) : 0
)

const visibleCards = computed(() => summary.value.filter((item) => item.total > 0))

const activeDateText = computed(() => {
  if (dateRange.value && dateRange.value.length === 2) {
    return `${dateRange.value[0]} 至 ${dateRange.value[1]}`
  }
  return '全部日期'
})

async function loadSummary() {
  loading.value = true
  try {
    const params = {}
    if (dateRange.value && dateRange.value.length === 2) {
      params.start_date = dateRange.value[0]
      params.end_date = dateRange.value[1]
    }
    summary.value = await api.getSummary(params)
  } catch (_) {
    summary.value = []
  } finally {
    loading.value = false
  }
}

function rowClassName({ row }) {
  return row.total > 0 ? 'clickable-row' : ''
}

function jumpToEmployee(row) {
  if (!row.total || !row.user?.id) return
  const el = document.getElementById(`employee-card-${row.user.id}`)
  if (!el) return
  highlightedId.value = row.user.id
  el.scrollIntoView({ behavior: 'smooth', block: 'start' })
  clearTimeout(highlightTimer)
  highlightTimer = setTimeout(() => {
    highlightedId.value = null
  }, 2400)
}

watch(dateRange, loadSummary)

onMounted(loadSummary)
</script>

<style scoped>
.summary-page {
  max-width: 1120px;
  margin: 0 auto;
}

.page-header {
  margin-bottom: 32px;
}

.page-title {
  font-size: 28px;
  font-weight: 700;
  color: #0f172a;
  margin: 0 0 8px;
  letter-spacing: -0.02em;
}

.page-desc {
  font-size: 15px;
  color: #64748b;
  margin: 0;
}

.summary-section {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.card {
  background: white;
  border-radius: 16px;
  padding: 20px 24px;
  box-shadow: 0 1px 3px rgb(0 0 0 / 0.06);
  border: 1px solid rgba(0, 0, 0, 0.04);
}

.card-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
}

.card-icon {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  background: linear-gradient(135deg, #0ea5e9 0%, #06b6d4 100%);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  flex-shrink: 0;
}

.card-icon.small {
  width: 34px;
  height: 34px;
  border-radius: 10px;
  font-size: 17px;
}

.card-title {
  font-size: 16px;
  font-weight: 600;
  color: #1e293b;
  margin: 0;
}

.card-sub {
  font-size: 12px;
  color: #94a3b8;
  margin: 2px 0 0;
}

.card-hint-inline {
  font-size: 13px;
  color: #94a3b8;
}

/* 筛选条 */
.filter-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
}

.filter-left {
  display: flex;
  align-items: center;
  gap: 12px;
}

.filter-actions {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.date-range-picker {
  width: 280px;
}

/* 总览 */
.overview-grid {
  display: grid;
  grid-template-columns: 1.6fr repeat(4, 1fr);
  gap: 16px;
}

.overview-card {
  display: flex;
  flex-direction: column;
  gap: 10px;
  justify-content: center;
  padding: 20px 22px;
}

.rate-card {
  background: linear-gradient(135deg, #f0f9ff 0%, #ecfeff 100%);
  border-color: rgba(14, 165, 233, 0.18);
}

.overview-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.overview-label {
  font-size: 13px;
  color: #64748b;
  font-weight: 500;
}

.overview-value {
  font-size: 30px;
  font-weight: 700;
  line-height: 1.1;
  color: #0f172a;
}

.overview-value.primary {
  color: #0ea5e9;
}

.overview-value.success {
  color: #10b981;
}

.overview-value.warning {
  color: #f59e0b;
}

.overview-value.muted {
  color: #94a3b8;
}

.overview-value.danger {
  color: #ef4444;
}

.overview-foot {
  font-size: 12px;
  color: #94a3b8;
}

.rate-main {
  display: flex;
  align-items: baseline;
}

.rate-value {
  font-size: 42px;
  font-weight: 800;
  line-height: 1;
  letter-spacing: -0.02em;
}

.rate-value.primary {
  color: #0ea5e9;
}

.rate-value.success {
  color: #10b981;
}

.rate-detail {
  font-size: 13px;
  color: #64748b;
  margin: 0;
}

/* 统计表 */
.table-card {
  padding: 20px 24px 24px;
}

.employee-table {
  width: 100%;
}

.employee-table :deep(.clickable-row) {
  cursor: pointer;
}

.employee-cell {
  display: inline-flex;
  align-items: center;
  gap: 10px;
}

.employee-cell.link .employee-name {
  color: #0ea5e9;
  font-weight: 600;
}

.employee-avatar {
  width: 32px;
  height: 32px;
  border-radius: 9px;
  background: linear-gradient(135deg, #0ea5e9, #06b6d4);
  color: white;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 14px;
}

.jump-icon {
  color: #0ea5e9;
  font-size: 14px;
}

.num-success {
  color: #10b981;
  font-weight: 600;
}

.num-warning {
  color: #f59e0b;
  font-weight: 600;
}

.num-muted {
  color: #94a3b8;
}

.num-danger {
  color: #ef4444;
  font-weight: 600;
}

.rate-cell {
  display: flex;
  align-items: center;
  gap: 10px;
}

.rate-cell :deep(.el-progress) {
  flex: 1;
}

.rate-cell-text {
  font-size: 13px;
  font-weight: 600;
  width: 48px;
  text-align: right;
}

.text-primary {
  color: #0ea5e9;
}

.text-success {
  color: #10b981;
}

/* 空状态 */
.empty-state {
  background: white;
  border-radius: 16px;
  padding: 80px 40px;
  text-align: center;
  border: 2px dashed #e2e8f0;
}

.empty-icon {
  width: 80px;
  height: 80px;
  margin: 0 auto 24px;
  border-radius: 20px;
  background: #f1f5f9;
  color: #94a3b8;
  font-size: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.empty-text {
  font-size: 18px;
  font-weight: 500;
  color: #64748b;
  margin: 0 0 8px;
}

.empty-hint {
  font-size: 14px;
  color: #94a3b8;
  margin: 0;
}

/* 员工图片对卡片 */
.summary-card {
  background: white;
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 1px 3px rgb(0 0 0 / 0.06);
  border: 1px solid rgba(0, 0, 0, 0.04);
  /* 顶部吸顶导航 64px，锚点跳转时留出间距 */
  scroll-margin-top: 84px;
  transition: box-shadow 0.3s, border-color 0.3s;
}

.summary-card.highlighted {
  border-color: #0ea5e9;
  box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.25), 0 8px 24px rgb(14 165 233 / 0.18);
  animation: highlight-pulse 1.2s ease-in-out 2;
}

@keyframes highlight-pulse {
  0%, 100% {
    box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.25), 0 8px 24px rgb(14 165 233 / 0.18);
  }
  50% {
    box-shadow: 0 0 0 6px rgba(14, 165, 233, 0.15), 0 10px 32px rgb(14 165 233 / 0.28);
  }
}

.summary-header {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  padding: 20px 24px;
  background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
  border-bottom: 1px solid #e2e8f0;
}

.summary-user {
  display: flex;
  align-items: center;
  gap: 14px;
}

.date-tag {
  margin-left: 4px;
}

.user-avatar {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  background: linear-gradient(135deg, #0ea5e9, #06b6d4);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 18px;
}

.summary-name {
  font-size: 20px;
  font-weight: 600;
  color: #1e293b;
  margin: 0;
}

.summary-stats {
  display: flex;
  gap: 32px;
  flex-wrap: wrap;
}

.stat {
  display: flex;
  align-items: baseline;
  gap: 6px;
}

.stat-label {
  font-size: 13px;
  color: #64748b;
}

.stat-value {
  font-size: 20px;
  font-weight: 700;
}

.stat-value.primary {
  color: #0ea5e9;
}

.stat-value.success {
  color: #10b981;
}

.stat-value.danger {
  color: #ef4444;
}

.stat-detail {
  font-size: 13px;
  color: #94a3b8;
}

.summary-records {
  padding: 20px 24px;
  display: grid;
  gap: 20px;
  grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
}

.record-item {
  background: #fafbfc;
  border-radius: 12px;
  padding: 16px;
  border: 1px solid #e2e8f0;
  transition: box-shadow 0.2s;
}

.record-item:hover {
  box-shadow: 0 4px 12px rgb(0 0 0 / 0.06);
}

.record-meta {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 12px;
  flex-wrap: wrap;
}

.record-seq {
  background: rgba(14, 165, 233, 0.12);
  color: #0ea5e9;
  padding: 2px 8px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
}

.record-date {
  font-size: 12px;
  color: #94a3b8;
  background: #f1f5f9;
  padding: 2px 8px;
  border-radius: 6px;
}

.badge-name {
  font-size: 13px;
  font-weight: 600;
  color: #334155;
}

.badge-score {
  font-size: 13px;
  font-weight: 600;
  color: #ef4444;
  margin-left: 4px;
}

.record-pair-desc {
  font-size: 12px;
  color: #94a3b8;
  margin: 0 0 10px;
}

.img-label {
  position: absolute;
  top: 6px;
  left: 6px;
  background: rgba(0, 0, 0, 0.6);
  color: white;
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 11px;
  z-index: 1;
}

.record-tag {
  margin-left: auto;
}

.record-images {
  display: flex;
  align-items: stretch;
  gap: 12px;
}

.record-img-wrap {
  position: relative;
  flex: 1;
  min-width: 0;
  border-radius: 8px;
  overflow: hidden;
  background: #e2e8f0;
  aspect-ratio: 4/3;
}

.record-img-wrap:first-of-type .img-label {
  background: rgba(239, 68, 68, 0.9);
}

.record-img-wrap:last-of-type .img-label {
  background: rgba(16, 185, 129, 0.9);
}

.record-img-wrap img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.record-arrow {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  font-size: 20px;
  color: #94a3b8;
}

.record-placeholder {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  color: #94a3b8;
}

@media (max-width: 960px) {
  .overview-grid {
    grid-template-columns: repeat(2, 1fr);
  }

  .rate-card {
    grid-column: 1 / -1;
  }
}
</style>
