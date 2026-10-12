<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Cache;
use App\Services\AdminAnalyticsService;

class AdminDashboardController
{
    private function jsonResponse($data)
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    public function index()
    {
        $db = Database::getInstance();
        $departments = $db->query("SELECT * FROM departments")->fetchAll();
        require BASE_PATH . '/src/Views/admin/dashboard.php';
    }

    public function clearCache()
    {
        Cache::clear();
        header('Location: /admin-dashboard');
        exit;
    }

    public function getCurrentLoadData()
    {
        $departmentId = $_GET['department_id'] ?? 'all';
        $cacheKey = "current_load_v2_{$departmentId}";

        $data = Cache::remember($cacheKey, 300, function() use ($departmentId) {
            $service = new AdminAnalyticsService();
            return $service->getCurrentLoad($departmentId);
        });

        $this->jsonResponse($data);
    }

    public function getThroughputData()
    {
        $period = $_GET['period'] ?? 'daily';
        $departmentId = $_GET['department_id'] ?? 'all';
        $cacheKey = "throughput_data_v2_{$period}_{$departmentId}";

        $data = Cache::remember($cacheKey, 600, function() use ($period, $departmentId) {
            $service = new AdminAnalyticsService();
            return $service->getThroughput($period, $departmentId);
        });

        $this->jsonResponse($data);
    }

    public function getAvgStepTimeByDepartmentData()
    {
        $isFull = isset($_GET['full']) ? 'full' : 'limited';
        $cacheKey = "avg_step_time_v2_{$isFull}";

        $data = Cache::remember($cacheKey, 600, function() use ($isFull) {
            $service = new AdminAnalyticsService();
            return $service->getAvgStepTimeByDepartment($isFull);
        });

        $this->jsonResponse($data);
    }

    public function getDepartmentalLoadVsTimeData()
    {
        $period = $_GET['period'] ?? 'daily';
        $departmentId = $_GET['department_id'] ?? 'all';
        $cacheKey = "dept_load_time_v2_{$period}_{$departmentId}";

        $data = Cache::remember($cacheKey, 600, function() use ($period, $departmentId) {
            $service = new AdminAnalyticsService();
            return $service->getDepartmentalLoadVsTime($period, $departmentId);
        });

        $this->jsonResponse($data);
    }

    public function getDeclineTrendData()
    {
        $period = $_GET['period'] ?? 'daily';
        $cacheKey = "decline_trends_v2_{$period}";

        $data = Cache::remember($cacheKey, 600, function() use ($period) {
            $service = new AdminAnalyticsService();
            return $service->getDeclineTrends($period);
        });

        $this->jsonResponse($data);
    }

    public function getDocumentStatusDistributionData()
    {
        $cacheKey = 'status_distribution_v2';
        
        $data = Cache::remember($cacheKey, 600, function() {
            $service = new AdminAnalyticsService();
            return $service->getStatusDistribution();
        });

        $this->jsonResponse($data);
    }

    public function getProcessingHotspotsData()
    {
        $cacheKey = 'processing_hotspots_v2';
        
        $data = Cache::remember($cacheKey, 600, function() {
            $service = new AdminAnalyticsService();
            return $service->getProcessingHotspots();
        });

        $this->jsonResponse($data);
    }

    public function getSubmissionDistrictsData()
    {
        $cacheKey = 'submission_districts_v2';
        
        $data = Cache::remember($cacheKey, 600, function() {
            $service = new AdminAnalyticsService();
            return $service->getSubmissionDistricts();
        });

        $this->jsonResponse($data);
    }

    public function getPeakIntakeHoursData()
    {
        $cacheKey = 'peak_intake_hours_v2';

        $data = Cache::remember($cacheKey, 600, function() {
            $service = new AdminAnalyticsService();
            return $service->getPeakIntakeHours();
        });

        $this->jsonResponse($data);
    }
}
