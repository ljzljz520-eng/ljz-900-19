<template>
  <div class="summary-page">
    <header class="page-header">
      <div>
        <h1 class="page-title">汇总看板</h1>
        <p class="page-desc">问题图与整改图成对展示，按 #key 从小到大排序，同一徽章（检查项+分值）共用</p>
      </div>
      <div class="filter-bar">
        <el-date-picker
          v-model="filterDate"
          type="date"
          placeholder="筛选检查日期"
          value-format="YYYY-MM-DD"
          clearable
          class="date-picker"
          @change="loadSummary"
        />
        <el-button :icon="Refresh" circle @click="loadSummary" :loading="loading" title="刷新实时数据" />
      </div>
    </header>

    <!-- 顶部总览：整改完成率按当前筛选数据实时计算 -->
    <section v-loading="loading" class="overview">
      <div class="overview-hero">
        <div class="ring" :style="ringStyle">
          <div class="ring-inner">
            <span class="ring-value">{{ overview.rate }}%</span>
            <span class="ring-label">整改完成率</span>
          </div>
        </div>
      </div>
      <div class="overview-metrics">
        <div class="metric">
          <span class="metric-label">问题总数</span>
          <span class="metric-value">{{ overview.total }}</span>
        </div>
        <div class="metric">
          <span class="metric-label">已整改</span>
          <span class="metric-value success">{{ overview.completed }}</span>
        </div>
        <div class="metric">
          <span class="metric-label">未整改</span>
          <span class="metric-value warning">{{ overview.pending }}</span>
        </div>
        <div class="metric">
          <span class="metric-label">总扣分</span>
          <span class="metric-value danger">-{{ overview.score }}分</span>
        </div>
        <div class="metric">
          <span class="metric-label">涉及员工</span>
          <span class="metric-value primary">{{ overview.employees }}人</span>
        </div>
      </div>
    </section>

    <!-- 按员工统计：点击某行跳转到对应图片对 -->
    <section v-if="summary.length > 0" class="employee-table-wrap">
      <el-table :data="summary" class="employee-table" @row-click="scrollToEmployee" :row-class-name="rowClassName">
        <el-table-column label="员工" min-width="140">
          <template #default="{ row }">
            <div class="cell-user">
              <div class="user-avatar sm">{{ (row.user?.name || '员')[0] }}</div>
              <span class="cell-user-name">{{ row.user?.name }}</span>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="问题总数" prop="total" width="100" align="center" />
        <el-table-column label="已整改" width="100" align="center">
          <template #default="{ row }">
            <span class="num-success">{{ row.completed }}</span>
          </template>
        </el-table-column>
        <el-table-column label="未整改" width="100" align="center">
          <template #default="{ row }">
            <span :class="row.pending > 0 ? 'num-warning' : 'num-muted'">{{ row.pending }}</span>
          </template>
        </el-table-column>
        <el-table-column label="总扣分" width="110" align="center">
          <template #default="{ row }">
            <span class="num-danger">-{{ row.total_score }}分</span>
          </template>
        </el-table-column>
        <el-table-column label="完成率" min-width="200">
          <template #default="{ row }">
            <div class="cell-progress">
              <el-progress
                :percentage="row.progress"
                :stroke-width="10"
                :status="row.progress >= 100 ? 'success' : ''"
              />
            </div>
          </template>
        </el-table-column>
        <el-table-column width="90" align="center">
          <template #default>
            <el-button type="primary" link size="small">
              查看图片<el-icon class="el-icon--right"><ArrowRight /></el-icon>
            </el-button>
          </template>
        </el-table-column>
      </el-table>
      <p class="table-hint">点击员工行可跳转到对应的问题图 / 整改图图片对</p>
    </section>

    <section v-loading="loading" class="summary-section">
      <div v-if="summary.length === 0 && !loading" class="empty-state">
        <div class="empty-icon">
          <el-icon><DataAnalysis /></el-icon>
        </div>
        <p class="empty-text">暂无汇总数据</p>
        <p class="empty-hint">{{ filterDate ? '当前筛选日期下没有检查记录，请更换日期' : '请在检查上传中创建记录' }}</p>
      </div>

      <div
        v-for="item in summary"
        :id="employeeCardId(item.user?.id)"
        :key="item.user?.id"
        class="summary-card"
        :class="{ 'card-flash': flashUserId === item.user?.id }"
      >
        <div class="summary-header">
          <div class="summary-user">
            <div class="user-avatar">{{ (item.user?.name || '员')[0] }}</div>
            <h2 class="summary-name">{{ item.user?.name }}</h2>
          </div>
          <div class="summary-stats">
            <div class="stat">
              <span class="stat-label">问题</span>
              <span class="stat-value primary">{{ item.total }}</span>
            </div>
            <div class="stat">
              <span class="stat-label">已整改</span>
              <span class="stat-value success">{{ item.completed }}</span>
            </div>
            <div class="stat">
              <span class="stat-label">未整改</span>
              <span class="stat-value" :class="item.pending > 0 ? 'warning' : 'muted'">{{ item.pending }}</span>
            </div>
            <div class="stat">
              <span class="stat-label">整改进度</span>
              <span class="stat-value" :class="item.progress >= 100 ? 'success' : 'primary'">
                {{ item.progress }}%
              </span>
              <span class="stat-detail">（{{ item.completed }}/{{ item.total }}）</span>
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
import { ref, computed, onMounted } from 'vue'
import { CircleCheck, DataAnalysis, ArrowRight, Refresh } from '@element-plus/icons-vue'
import { api, apiBase } from '@/api/request'

const loading = ref(true)
const summary = ref([])
const filterDate = ref('')
const flashUserId = ref(null)

// 完成率及各项汇总均由接口返回的实时记录统计得出，不写死任何数字
const overview = computed(() => {
  const total = summary.value.reduce((s, it) => s + (it.total || 0), 0)
  const completed = summary.value.reduce((s, it) => s + (it.completed || 0), 0)
  const pending = total - completed
  const score = summary.value.reduce((s, it) => s + (it.total_score || 0), 0)
  return {
    total,
    completed,
    pending,
    score,
    employees: summary.value.length,
    rate: total > 0 ? Math.round((completed / total) * 1000) / 10 : 0,
  }
})

const ringStyle = computed(() => ({
  background: `conic-gradient(${overview.value.rate >= 100 ? '#10b981' : '#0ea5e9'} ${overview.value.rate * 3.6}deg, #e2e8f0 0deg)`,
}))

function imageUrl(path) {
  if (!path) return ''
  const base = apiBase() || (typeof window !== 'undefined' ? window.location.origin : '')
  return path.startsWith('http') ? path : (base.replace(/\/$/, '') + path)
}

async function loadSummary() {
  loading.value = true
  try {
    summary.value = await api.getSummary(filterDate.value ? { check_date: filterDate.value } : {})
  } catch (_) {
    summary.value = []
  } finally {
    loading.value = false
  }
}

function employeeCardId(userId) {
  return userId != null ? `employee-card-${userId}` : ''
}

function rowClassName({ row }) {
  return flashUserId.value === row.user?.id ? 'row-flash' : ''
}

function scrollToEmployee(row) {
  const id = employeeCardId(row.user?.id)
  if (!id) return
  const el = document.getElementById(id)
  if (!el) return
  el.scrollIntoView({ behavior: 'smooth', block: 'start' })
  flashUserId.value = row.user?.id
  // 高亮提示当前定位的员工
  window.setTimeout(() => {
    if (flashUserId.value === row.user?.id) flashUserId.value = null
  }, 2000)
}

onMounted(loadSummary)
</script>

<style scoped>
.summary-page {
  max-width: 1120px;
  margin: 0 auto;
}

.page-header {
  margin-bottom: 24px;
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  justify-content: space-between;
  gap: 16px;
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

.filter-bar {
  display: flex;
  align-items: center;
  gap: 10px;
}

.date-picker {
  width: 180px;
}

/* 顶部总览 */
.overview {
  background: white;
  border-radius: 16px;
  padding: 24px 28px;
  margin-bottom: 24px;
  box-shadow: 0 1px 3px rgb(0 0 0 / 0.06);
  border: 1px solid rgba(0, 0, 0, 0.04);
  display: flex;
  align-items: center;
  gap: 36px;
  flex-wrap: wrap;
}

.overview-hero {
  flex-shrink: 0;
}

.ring {
  width: 120px;
  height: 120px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 0.4s ease;
}

.ring-inner {
  width: 96px;
  height: 96px;
  border-radius: 50%;
  background: white;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

.ring-value {
  font-size: 26px;
  font-weight: 800;
  color: #0f172a;
  line-height: 1.1;
}

.ring-label {
  font-size: 12px;
  color: #64748b;
  margin-top: 2px;
}

.overview-metrics {
  flex: 1;
  min-width: 280px;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(110px, 1fr));
  gap: 20px;
}

.metric {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.metric-label {
  font-size: 13px;
  color: #64748b;
}

.metric-value {
  font-size: 26px;
  font-weight: 700;
  color: #0f172a;
  line-height: 1.1;
}

.metric-value.success {
  color: #10b981;
}

.metric-value.warning {
  color: #f59e0b;
}

.metric-value.danger {
  color: #ef4444;
}

.metric-value.primary {
  color: #0ea5e9;
}

/* 员工统计表 */
.employee-table-wrap {
  background: white;
  border-radius: 16px;
  padding: 8px 16px 14px;
  margin-bottom: 24px;
  box-shadow: 0 1px 3px rgb(0 0 0 / 0.06);
  border: 1px solid rgba(0, 0, 0, 0.04);
}

.employee-table {
  cursor: pointer;
}

.table-hint {
  margin: 8px 4px 2px;
  font-size: 12px;
  color: #94a3b8;
}

.cell-user {
  display: flex;
  align-items: center;
  gap: 10px;
}

.user-avatar.sm {
  width: 32px;
  height: 32px;
  border-radius: 9px;
  font-size: 14px;
}

.cell-user-name {
  font-weight: 600;
  color: #1e293b;
}

.cell-progress {
  padding-right: 8px;
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
  color: #cbd5e1;
}

.num-danger {
  color: #ef4444;
  font-weight: 600;
}

:deep(.row-flash) {
  background-color: #ecfeff !important;
}

.summary-section {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

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

.summary-card {
  background: white;
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 1px 3px rgb(0 0 0 / 0.06);
  border: 1px solid rgba(0, 0, 0, 0.04);
  scroll-margin-top: 16px;
}

.summary-card.card-flash {
  outline: 3px solid #0ea5e9;
  outline-offset: -1px;
  animation: card-flash-anim 2s ease;
}

@keyframes card-flash-anim {
  0% {
    box-shadow: 0 0 0 6px rgba(14, 165, 233, 0.25);
  }
  100% {
    box-shadow: 0 1px 3px rgb(0 0 0 / 0.06);
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
  gap: 28px;
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

.stat-value.warning {
  color: #f59e0b;
}

.stat-value.muted {
  color: #cbd5e1;
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

.record-item-name {
  font-size: 13px;
  color: #64748b;
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

.text-success {
  color: #10b981;
  font-size: 24px;
}

.text-muted {
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
</style>
