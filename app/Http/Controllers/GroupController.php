<?php

namespace App\Http\Controllers;

use Validator;
use App\Group;
use App\Http\Datatables\GroupDatatable;
use Illuminate\Http\Request;

class GroupController extends Controller {

    private $nav = 'groups';

    function __construct()
    {
    }

    public function index() {
        if(!$this->checkMenuPermission($this->nav, 'show')) {
            $this->setAlertPermission();
            return redirect(route("home"));
        }

        $params = [
            'base_url' => route('groups'),
            'dataload_url' => route('groups_load'),
            'page_title' => "Groups",
            'title' => "group",
            'titles' => "groups",
            'icon' => $this->getIcons($this->nav),
            'icons' => $this->getIcons($this->nav, true),
            'create' => false,
            'filter' => false,
            'unsortable' => "0,1,2",
            'columns' => [
                [ "title" => "#", "width" => "5%", "filter" => ""],
                [ "title" => "name", "filter" => $this->filterText("name")],
                [ "title" => "action", "filter" => $this->filterAction(true)],
            ],
        ];
        $params['message'] = $this->getAlert();
        $params['messageType'] = $this->getAlertCSSClass();

        return view('table', $params)->withNav($this->nav);
    }

    public function datatable(Request $request) {
        $ajax_table = new GroupDatatable;
        return $ajax_table->table($request);
    }

    public function create() {
        if(!$this->checkMenuPermission($this->nav, 'create')) {
            $this->setAlertPermission();
            return redirect(route($this->nav));
        }
        $group['id'] = "";
        return view('groups-create', compact('group'))->withNav($this->nav);
    }

    public function edit(Request $request) {
        if(!$this->checkMenuPermission($this->nav, 'update')) {
            $this->setAlertPermission();
        }else {
            $group = Group::find($request->group);
            if ($group) {
                return view('groups-create', compact('group'))->withNav($this->nav);
            }
        }
        return redirect(route($this->nav));
    }

    public function update(Request $request) {
        if($this->checkMenuPermission($this->nav, 'create') || $this->checkMenuPermission($this->nav, 'update')) {
            $validator = Validator::make($request->all(), [
                'group_name' => 'required|max:255',
            ]);
            if ($validator->fails()) {
                $this->errors = $validator->messages();
                return $this->validationError();
            }
            if ($request->group) {
                $group = Group::find($request->group);
            } else {
                $group = new Group;
            }
            $group->name = $request->input('group_name');

            //    $group->description = $request->input('description');
            if ($group->save()) {
                $this->success();
                $default = " Group Name '" . $group->name . "'";
                $this->message = ($request->group) ? "$default Updated" : "New $default Added";
                if (!$request->group) {
                    $data['redirect_to'] = route('groups');
                    $this->setAlert($this->message);
                    $this->setAlertCSSClass("success");
                }
            }
            $this->data = isset($data) ? $data : [];
        }
        return $this->output();
    }


    public function createPermission(Group $group) {
        if(!$this->checkMenuPermission($this->nav, 'permission')) {
            $this->setAlertPermission();
        }else {
            if($group) {
                $pItems = $this->permissions();
                return view('groups-permission', compact('group', 'pItems'))->withNav($this->nav);
            }
        }
        return redirect(route($this->nav));
    }

    public function updatePermission(Request $request) {
        // var_dump($this->nav, $this->checkMenuPermission($this->nav, 'permission'), $request->permissions, json_encode($request->permissions));
        // dd($request->all());
        if($this->checkMenuPermission($this->nav, 'permission')) {
            if($request->group_id) {
                $group = Group::find($request->group_id);

                if($group) {
                    $group->permissions = json_encode($request->permissions);
                    if ($group->save()) {
                        $this->success();
                        $this->message = "Group permission updated";
                    }
                    $this->data = isset($data) ? $data : [];
                }
            }
        }
        return $this->output();
    }
}
