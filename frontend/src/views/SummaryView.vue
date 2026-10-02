<template>
  <div class="summary-page">
    <header class="page-header">
      <div class="page-header-main">
        <h1 class="page-title">汇总看板</h1>
        <p class="page-desc">问题图与整改图成对展示，按 #key 从小到大排序，同一徽章（检查项+分值）共用</p>
      </div>
      <div class="page-filter">
        <el-date-picker
          v-model="filterDate"
          type="date"
          placeholder="筛选检查日期"
          value-format="YYYY-MM-DD"
          clearable
          class="date-picker"
        />
      </div>
    </header>

    <!-- 整改完成率总览：全部由接口实时数据计算 -->
    <section v-loading="loading" class="overview-section">
      <div class="overview-rate-card">
        <span class="overview-label">整改完成率</span>
        <span class="overview-rate" :class="overall.rate >= 100 ? 'success' : 'primary'">
          {{ overall.rate }}%
        </span>
        <span class="overview-rate-detail">{{ overall.completed }}/{{ overall.total }}</span>
        <el-progress
          :percentage="overall.rate"
          :stroke-width="10"
          :color="overall.rate >= 100 ? '#10b981' : '#0ea5e9'"
          class="overview-progress"
        />
      </div>
      <div class="overview-cards">
        <div class="overview-card">
          <span class="overview-card-value">{{ overall.total }}</span>
          <span class="overview-card-label">问题总数</span>
        </div>
        <div class="overview-card">
          <span class="overview-card-value success">{{ overall.completed }}</span>
          <span class="overview-card-label">已整改</span>
        </div>
        <div class="overview-card">
          <span class="overview-card-value warning">{{ overall.pending }}</span>
          <span class="overview-card-label">未整改</span>
        </div>
        <div class="overview-card">
          <span class="overview-card-value danger">-{{ overall.totalScore }}</span>
          <span class="overview-card-label">总扣分</span>
        </div>
      </div>
    </section>

    <!-- 按员工统计：点击行跳转到对应图片对 -->
    <section v-if="summary.length > 0" class="emp-stats-section">
      <h2 class="section-title">按员工统计</h2>
      <div class="emp-stats-table-wrap">
        <table class="emp-stats-table">
          <thead>
            <tr>
              <th>员工</th>
              <th>问题总数</th>
              <th>已整改</th>
              <th>未整改</th>
              <th>总扣分</th>
              <th>完成率</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="item in summary"
              :key="item.user?.id"
              class="emp-row"
              @click="scrollToEmployee(item.user?.id)"
            >
              <td>
                <div class="emp-cell-user">
                  <span class="emp-avatar">{{ (item.user?.name || '员')[0] }}</span>
                  <span class="emp-name">{{ item.user?.name }}</span>
                </div>
              </td>
              <td>{{ item.total }}</td>
              <td class="cell-success">{{ item.completed }}</td>
              <td class="cell-warning">{{ item.pending }}</td>
              <td class="cell-danger">-{{ item.total_score }}</td>
              <td>
                <span class="emp-progress" :class="item.progress >= 100 ? 'success' : 'primary'">
                  {{ item.progress }}%
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <p class="emp-stats-hint">点击员工可跳转到对应图片对</p>
    </section>

    <section v-loading="loading" class="summary-section">
      <div v-if="summary.length === 0 && !loading" class="empty-state">
        <div class="empty-icon">
          <el-icon><DataAnalysis /></el-icon>
        </div>
        <p class="empty-text">暂无汇总数据</p>
        <p class="empty-hint">请在检查上传中创建记录</p>
      </div>

      <div
        v-for="item in summary"
        :key="item.user?.id"
        :ref="(el) => setCardRef(item.user?.id, el)"
        class="summary-card"
        :class="{ 'card-highlight': highlightId === item.user?.id }"
      >
        <div class="summary-header">
          <div class="summary-user">
            <div class="user-avatar">{{ (item.user?.name || '员')[0] }}</div>
            <h2 class="summary-name">{{ item.user?.name }}</h2>
          </div>
          <div class="summary-stats">
            <div class="stat">
              <span class="stat-label">整改进度</span>
              <span class="stat-value" :class="item.progress >= 100 ? 'success' : 'primary'">
                {{ item.progress }}%
              </span>
              <span class="stat-detail">（{{ item.completed }}/{{ item.total }}）</span>
            </div>
            <div class="stat">
              <span class="stat-label">未整改</span>
              <span class="stat-value warning">{{ item.pending }}</span>
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
import { ref, computed, onMounted, watch } from 'vue'
import { CircleCheck, DataAnalysis } from '@element-plus/icons-vue'
import { api, apiBase } from '@/api/request'

const loading = ref(true)
const summary = ref([])
const filterDate = ref('')
const highlightId = ref(null)
const cardRefs = new Map()
let highlightTimer = null

// 总览统计：完全基于接口返回的实时数据计算，不写死
const overall = computed(() => {
  let total = 0
  let completed = 0
  let totalScore = 0
  for (const item of summary.value) {
    total += item.total || 0
    completed += item.completed || 0
    totalScore += item.total_score || 0
  }
  return {
    total,
    completed,
    pending: total - completed,
    totalScore,
    rate: total > 0 ? Math.round((completed / total) * 1000) / 10 : 0,
  }
})

function setCardRef(userId, el) {
  if (userId == null) return
  if (el) {
    cardRefs.set(userId, el)
  } else {
    cardRefs.delete(userId)
  }
}

function scrollToEmployee(userId) {
  if (userId == null) return
  const el = cardRefs.get(userId)
  if (!el) return
  el.scrollIntoView({ behavior: 'smooth', block: 'start' })
  highlightId.value = userId
  if (highlightTimer) clearTimeout(highlightTimer)
  highlightTimer = setTimeout(() => {
    highlightId.value = null
  }, 1600)
}

function imageUrl(path) {
  if (!path) return ''
  const base = apiBase() || (typeof window !== 'undefined' ? window.location.origin : '')
  return path.startsWith('http') ? path : (base.replace(/\/$/, '') + path)
}

async function loadSummary() {
  loading.value = true
  try {
    summary.value = await api.getSummary({ check_date: filterDate.value || undefined })
  } catch (_) {
    summary.value = []
  } finally {
    loading.value = false
  }
}

// 筛选日期变化后重新拉取，统计与图片对同步更新
watch(filterDate, loadSummary)

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

.page-header-main {
  min-width: 0;
}

.page-filter {
  flex-shrink: 0;
}

.date-picker {
  width: 180px;
}

/* 整改完成率总览 */
.overview-section {
  display: flex;
  flex-wrap: wrap;
  gap: 20px;
  margin-bottom: 24px;
}

.overview-rate-card {
  flex: 1 1 320px;
  min-width: 280px;
  background: white;
  border-radius: 16px;
  padding: 24px;
  border: 1px solid rgba(0, 0, 0, 0.04);
  box-shadow: 0 1px 3px rgb(0 0 0 / 0.06);
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.overview-label {
  font-size: 14px;
  color: #64748b;
}

.overview-rate {
  font-size: 40px;
  font-weight: 700;
  line-height: 1.1;
  letter-spacing: -0.02em;
}

.overview-rate.primary {
  color: #0ea5e9;
}

.overview-rate.success {
  color: #10b981;
}

.overview-rate-detail {
  font-size: 13px;
  color: #94a3b8;
}

.overview-progress {
  margin-top: 4px;
}

.overview-cards {
  flex: 2 1 480px;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: 16px;
}

.overview-card {
  background: white;
  border-radius: 16px;
  padding: 20px;
  border: 1px solid rgba(0, 0, 0, 0.04);
  box-shadow: 0 1px 3px rgb(0 0 0 / 0.06);
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  justify-content: center;
  gap: 6px;
}

.overview-card-value {
  font-size: 28px;
  font-weight: 700;
  color: #1e293b;
}

.overview-card-value.success {
  color: #10b981;
}

.overview-card-value.warning {
  color: #f59e0b;
}

.overview-card-value.danger {
  color: #ef4444;
}

.overview-card-label {
  font-size: 13px;
  color: #64748b;
}

/* 按员工统计表 */
.emp-stats-section {
  background: white;
  border-radius: 16px;
  padding: 20px 24px;
  border: 1px solid rgba(0, 0, 0, 0.04);
  box-shadow: 0 1px 3px rgb(0 0 0 / 0.06);
  margin-bottom: 24px;
}

.section-title {
  font-size: 17px;
  font-weight: 600;
  color: #1e293b;
  margin: 0 0 16px;
}

.emp-stats-table-wrap {
  overflow-x: auto;
}

.emp-stats-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 14px;
}

.emp-stats-table th {
  text-align: left;
  font-size: 13px;
  font-weight: 500;
  color: #64748b;
  padding: 10px 12px;
  border-bottom: 1px solid #e2e8f0;
  white-space: nowrap;
}

.emp-stats-table td {
  padding: 12px;
  border-bottom: 1px solid #f1f5f9;
  color: #334155;
  white-space: nowrap;
}

.emp-stats-table tbody tr.emp-row {
  cursor: pointer;
  transition: background 0.15s;
}

.emp-stats-table tbody tr.emp-row:hover {
  background: #f0f9ff;
}

.emp-stats-table tbody tr:last-child td {
  border-bottom: none;
}

.emp-cell-user {
  display: flex;
  align-items: center;
  gap: 10px;
}

.emp-avatar {
  width: 30px;
  height: 30px;
  border-radius: 8px;
  background: linear-gradient(135deg, #0ea5e9, #06b6d4);
  color: white;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  font-size: 13px;
  flex-shrink: 0;
}

.emp-name {
  font-weight: 600;
  color: #1e293b;
}

.cell-success {
  color: #10b981;
  font-weight: 600;
}

.cell-warning {
  color: #f59e0b;
  font-weight: 600;
}

.cell-danger {
  color: #ef4444;
  font-weight: 600;
}

.emp-progress {
  font-weight: 700;
}

.emp-progress.primary {
  color: #0ea5e9;
}

.emp-progress.success {
  color: #10b981;
}

.emp-stats-hint {
  font-size: 12px;
  color: #94a3b8;
  margin: 12px 0 0;
}

.summary-card {
  scroll-margin-top: 88px;
  transition: box-shadow 0.3s, border-color 0.3s;
}

.summary-card.card-highlight {
  border-color: #0ea5e9;
  box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.25), 0 8px 24px rgba(14, 165, 233, 0.15);
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
  gap: 32px;
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
