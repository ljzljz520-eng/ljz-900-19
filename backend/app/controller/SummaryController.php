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
    private function normalizeDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $value = (string) $value;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return '__INVALID__';
        }
        $dt = \DateTime::createFromFormat('Y-m-d', $value);
        if (!$dt || $dt->format('Y-m-d') !== $value) {
            return '__INVALID__';
        }
        return $value;
    }

    public function index(): Response
    {
        try {
            $startDate = $this->normalizeDate(Request::param('start_date'));
            $endDate = $this->normalizeDate(Request::param('end_date'));
            if ($startDate === '__INVALID__' || $endDate === '__INVALID__') {
                return api_json(['code' => 400, 'message' => '日期格式错误（应为 YYYY-MM-DD）', 'data' => null]);
            }
            if ($startDate && $endDate && $startDate > $endDate) {
                return api_json(['code' => 400, 'message' => '开始日期不能晚于结束日期', 'data' => null]);
            }

            $users = User::where('role', 'employee')->order('id', 'asc')->with(['records' => function ($q) use ($startDate, $endDate) {
                $q->with('item')->order('check_date', 'asc')->order('sequence_key', 'asc');
                if ($startDate) {
                    $q->where('check_date', '>=', $startDate);
                }
                if ($endDate) {
                    $q->where('check_date', '<=', $endDate);
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
