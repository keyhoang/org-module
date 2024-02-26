<?php

namespace KeyHoang\OrgModule\Services;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use KeyHoang\OrgModule\Models\Department;
use KeyHoang\OrgModule\Models\DepartmentNoSQL;
use KeyHoang\OrgModule\Models\User;
use KeyHoang\OrgModule\Models\UserNoSQL;
use YaangVu\LaravelBase\Base\BaseService;

/**
 * @Author      hungnv1
 * @Date        DummyDate
 */
class DepartmentService extends BaseService
{
    protected Model $userModel;
    private string  $mongodb   = 'mongodb';
    private bool    $isMongodb = false;

    public function __construct(private Model $model = new Department(), private readonly ?string $alias = null)
    {
        $this->isMongodb = (config('database.default') == $this->mongodb);

        $this->model     = $this->isMongodb ? new DepartmentNoSQL() : new Department();
        $this->userModel = $this->isMongodb ? new UserNoSQL() : new User();
        parent::__construct($this->model, $this->alias);
    }

    public function sync($department): bool
    {
        if ($department?->deleted_at ?? false) {
            $this->model->query()->where('code', '=', $department->code)->delete();
            Log::info("Delete department code: " . $department->code . "  Success");

            return true;
        }

        $departmentModel = $this->model->query()->where('code', '=', $department->code)->first();
        if (!$departmentModel) {
            $departmentModel = $this->isMongodb ? new DepartmentNoSQL() : new Department();
        }

        $departmentModel->code          = $department->code;
        $departmentModel->name          = $department->name;
        $departmentModel->level         = $department->level;
        $departmentModel->is_unit       = $department->is_unit;
        $departmentModel->is_department = $department->is_department;

        if ($department->parent) {
            $parent = $this->model->query()->where('code', '=', $department->parent->code)
                                  ->first();

            $departmentModel->parent_id = $this->isMongodb ? $parent?->_id : $parent?->id;
        }

        if ($department->department_head) {
            $departmentHead                      = $this->userModel->query()
                                                                   ->where('sso_id', '=',
                                                                           $department->department_head->sso_id)
                                                                   ->first();
            $departmentModel->department_head_id = $this->isMongodb ? $departmentHead?->_id : $departmentHead?->id;
        }

        try {
            $departmentModel->save();
            Log::info("Sync department code: " . $department->code . "  Success");
        } catch (Exception $e) {
            Log::info("Sync Fail : " . $e->getMessage());
        }

        return true;
    }
}
