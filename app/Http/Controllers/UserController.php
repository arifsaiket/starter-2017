<?php

namespace App\Http\Controllers;

use Validator;
use Auth;
use Hash;
use App\User;
use App\District;
use App\Office;
use App\Group;
use App\Project;
use App\Http\Datatables\UserDatatable;
use Illuminate\Http\Request;

class UserController extends Controller {

    private $nav = 'users';

    function __construct()
    {
        $this->middleware('menu');
    }

    public function index() {
        if(!$this->checkMenuPermission($this->nav, 'show')) {
            $this->setAlertPermission();
            return redirect(route("home"));
        }

        $params = [
            'base_url' => route('users'),
            'dataload_url' => route('users_load'),
            'page_title' => "Users",
            'title' => "user",
            'titles' => "users",
            'icon' => $this->getIcons($this->nav),
            'icons' => $this->getIcons($this->nav, true),
            'create' => $this->checkMenuPermission($this->nav, 'create'),
            'filter' => true,
            'unsortable' => "0,3,4,5,7",
            'columns' => [
                [ "title" => "#", "width" => "5%", "filter" => ""],
                [ "title" => "name", "filter" => $this->filterText("name")],
                [ "title" => "email", "filter" => $this->filterText("email")],
                [ "title" => "group", "filter" => $this->filterText("group")],
                [ "title" => "office", "filter" => $this->filterText("office")],
                [ "title" => "district", "filter" => $this->filterText("district")],
                [ "title" => "updated_time", "filter" => $this->filterDateRange()],
                [ "title" => "action", "filter" => $this->filterAction()],
            ],
        ];
        $params['message'] = $this->getAlert();
        $params['messageType'] = $this->getAlertCSSClass();

        return view('table', $params)->withNav($this->nav);
    }

    public function datatable(Request $request) {
        $ajax_table = new UserDatatable;
        return $ajax_table->table($request);
    }

    public function profile(){

        $profile = Auth::user();

        $params = [
            'profile' => $profile
            
        ];

        return view('profile', $params);
    }
    public function create() {
        if(!$this->checkMenuPermission($this->nav, 'create')) {
            $this->setAlertPermission();
            return redirect(route($this->nav));
        }
        $params['user']['id'] = "";
        $params['groups'] = Group::all();
        //if(session()->has('district')) {
        if(!$this->checkMenuPermission('districts', 'update')) {
            $params['offices'] = Office::whereDistrictId(Auth::user()->district_id)->get();
        }else {
            $params['districts'] = District::all();
        }
        //dd($params['offices']);
        return view('users-create', $params)->withNav($this->nav);
    }

    public function edit(Request $request) {
        if(!$this->checkMenuPermission($this->nav, 'update')) {
            $this->setAlertPermission();
        }else {
            $user = User::find($request->user);
            $params['user'] = $user;
            $params['groups'] = Group::all();
            if(!$this->checkMenuPermission('districts', 'update')) {
                $params['offices'] = Office::whereDistrictId(Auth::user()->district_id)->get();
            }else {
                $district_id = (isset($user->office->district) ? $user->office->district->id : '0');
                $params['districts'] = District::all();
                $params['offices'] = Office::whereDistrictId($district_id)->get();
            }
            if ($user) {
                return view('users-create', $params)->withNav($this->nav);
            }
        }
        return redirect(route($this->nav));
    }

    public function update(Request $request) {
        if($this->checkMenuPermission($this->nav, 'create') || $this->checkMenuPermission($this->nav, 'update')) {
            $validation = [
                'office'        => 'required',
                'group'         => 'required',
                'name'          => 'required|string|max:255',
                'name_bangla'   => 'required|string|max:255',
            ];
            if (!$request->user) {
                $validation['email']= 'required|string|email|max:255|unique:users';
                $validation['password']= 'required|string|max:255';
            }
            if($this->checkMenuPermission('districts', 'update')) {
                $validation['district']= 'required';
            }

            $validator = Validator::make($request->all(), $validation);
            if ($validator->fails()) {
                $this->errors = $validator->messages();
                return $this->validationError();
            }
            if ($request->user) {
                $user = User::find($request->user);
            } else {
                $user = new User;
            }

            $user->district_id = ($this->checkMenuPermission('districts', 'update')) ? $request->input('district') : \Auth::user()->district->id;
            $user->office_id = $request->input('office');
            $user->group_id = $request->input('group');

            $user->name = $request->input('name');
            $user->name_bangla = $request->input('name_bangla');

            if($request->has('email')) {
                $user->email = $request->input('email');
            }
            if($request->has('password')) {
                $user->password = bcrypt($request->input('password'));
            }

            if ($user->save()) {
                $this->success();
                $default = " User Name '" . $user->name . "'";
                $this->message = ($request->user) ? "$default Updated" : "New $default Added";
                if (!$request->user) {
                    $data['redirect_to'] = route('users');
                    $this->setAlert($this->message);
                    $this->setAlertCSSClass("success");
                }
            }
            $this->data = isset($data) ? $data : [];
        }
        return $this->output();
    }



    public function profileUpdate(Request $request) {
        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:255',
            'name_bangla' => 'required|string|max:255',
            'email'       => 'required|string|email|max:255',
            'mobile'      => 'required|string|max:11',
        ]);
        if ($validator->fails()) {
            $this->errors = $validator->messages();
            return $this->validationError();
        }

        $user = User::find(Auth::user()->id);

        $user->name = $request->input('name');
        $user->name_bangla = $request->input('name_bangla');
        $user->email = $request->input('email');
        $user->mobile = $request->input('mobile');

        if ($user->save()) {
            $this->success();
            $this->message = "Profile Updated";
        }
        return $this->output();

    }

    public function pictureUpdate(Request $request) {

        $validator = Validator::make($request->all(), [
            'picture' => 'required|mimes:jpeg,bmp,png',
        ]);
        if ($validator->fails()) {
            $this->errors = $validator->messages();
            return $this->validationError();
        }

        $user = User::find(Auth::user()->id);
        $pictureFile = $request->file('picture');
        $picture;

        if($user->group->id == 3 and $user->group->name == "Deputy Commissioner"){
            $picture = $this->saveInStorage($pictureFile, 'images', $user->district->name.'_DC');
            $image = $pictureFile;
            $imageName = $user->district->name.'_DC.'.$image->getClientOriginalExtension();
            $image->move(public_path('/images'), $imageName);
        }else{
            $picture = $this->saveInStorage($pictureFile, 'images');
        }
        //$picture = $this->saveInStorage($pictureFile, 'images');

        $user->picture = $picture;

        if ($user->save()) {
            // $project = Project::find(2);
            // if($project){
            //     $project_path = $project->url;
            //     $path = 'download_dc_image';
            //     $project_path = $project->url;
            //     $client1 = new \GuzzleHttp\Client();
            //     //dd($project_path.'api/v1/'.$path.'/'.$user->id);
            //     //$res = $client->request('GET', $project_path.'api/v1/'.$path.'/'.$user->id);
            //     $res = $client1->request('GET', 'http://localhost:8088/api/v1/download_dc_image/2');
            //     if($res->getStatusCode()==200){
            //         //return;
            //         dd($res->getBody());
            //     }

            // }
            $this->success();
            $this->message = "Picture Updated";
        }
        return $this->output();
    }

    public function signatureUpdate(Request $request){

        $validator = Validator::make($request->all(), [
            'signature' => 'required|mimes:jpeg,bmp,png',
        ]);
        if ($validator->fails()) {
            $this->errors = $validator->messages();
            return $this->validationError();
        }

        $user = User::find(Auth::user()->id);

        // Save 'signature' //
        $signature = $this->saveInStorage($request->file('signature'),'signatures');

        $user->signature = $signature;

        if ($user->save()) {
            $this->success();
            $this->message = "Signature Updated";
        }
        return $this->output();

    }

    public function passwordUpdate(Request $request) {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string|max:255',
            'new_password' => 'required|string|max:255',
            'confirm_password' => 'required|string|max:255',
        ]);
        if ($validator->fails()) {
            $this->errors = $validator->messages();
            return $this->validationError();
        }
        $user = User::find(Auth::user()->id);

        if (Hash::check($request->input('current_password'), $user->getAuthPassword())) {
            if($request->input('new_password') != $request->input('confirm_password')) {
                $this->message = "Password mismatched!";
            }

            $user->password = bcrypt($request->input('new_password'));

            if ($user->save()) {
                $this->success();
                $this->message = "Password Updated";
            }
        }else{
            $this->message = "You Entered Current Password Wrong!";
        }
        return $this->output();
    }

}