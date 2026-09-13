<?php
declare(strict_types=1);
namespace app\controller;
use app\model\Record;
use app\model\User;
use think\facade\Log;
use think\facade\Request;
use think\Response;
class SummaryController
{
    private function normalizeCheckDate($checkDate): ?string
    {
        if (!$checkDate) {
            return null;
        }
        $checkDate = (string) $checkDate;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkDate)) {
            return '__INVALID__';
        }
        $dt = \DateTime::createFromFormat('Y-m-d', $checkDate);
        if (!$dt || $dt->format('Y-m-d') !== $checkDate) {
            return '__INVALID__';
        }
        return $checkDate;
    }

    public function index(): Response
    {
        try {
            $checkDate = $this->normalizeCheckDate(Request::param('check_date'));
            if ($checkDate === '__INVALID__') {
                return api_json(['code' => 400, 'message' => 'check_date 格式错误（应为 YYYY-MM-DD）', 'data' => null]);
            }
            $users = User::where('role', 'employee')->with(['records' => function ($q) use ($checkDate) {
                $q->with('item')->order('sequence_key', 'asc');
                if ($checkDate) {
                    $q->where('check_date', $checkDate);
                }
            }])->select();
            $data = [];
            foreach ($users as $user) {
                $records = $user->records;
                $total = 0;
                $completed = 0;
                $totalScore = 0;
                foreach ($records as $r) {
                    $total++;
                    if ($r->status === 'completed') {
                        $completed++;
                    }
                    $totalScore += (int) ($r->item_score_snapshot ?? ($r->item->score ?? 0));
                }
                // 筛选日期下没有问题记录的员工不参与统计，避免空数据干扰
                if ($total === 0) {
                    continue;
                }
                $data[] = [
                    'user' => $user,
                    'records' => $records,
                    'total' => $total,
                    'completed' => $completed,
                    'pending' => $total - $completed,
                    'progress' => $total > 0 ? round($completed / $total * 100, 1) : 0,
                    'total_score' => $totalScore,
                ];
            }
            return api_json(['code' => 0, 'message' => 'ok', 'data' => $data]);
        } catch (\Throwable $e) {
            Log::error('SummaryController@index: ' . $e->getMessage());
            return api_json(['code' => 500, 'message' => '服务器错误', 'data' => null]);
        }
    }
}
