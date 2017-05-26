<?php

namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Http\Requests;
use App\Models\TalukaModel;
use App\Models\CountryModel;
use App\Models\StateModel;

use App\Events\ActivityLogEvent;

use Illuminate\Http\Request;
use Session;
use Validator;
use App\Common\Services\LanguageService;
use Flash;
use Datatables;
use Sentinel;

class TalukaController extends Controller
{

    /*
    | Constructor : creates instances of model class & handles the admin authantication
    | auther      : Paras Kale 
    | Date        : 29/08/2016
    | @return \Illuminate\Http\Response
    */

    public function __construct(CountryModel $countries,
                                StateModel $state,
                                TalukaModel $taluka,
                                LanguageService $langauge
                                )
    {
        $this->CountryModel       = $countries;
        $this->StateModel         = $state;
        $this->TalukaModel        = $taluka;
        $this->LanguageService    = $langauge;        
        $this->BaseModel          = $this->TalukaModel;
        $this->arr_view_data      = [];
        $this->module_url_path    = url(config('app.project.admin_panel_slug')."/talukas");
        $this->module_title       = "Talukas";
        $this->module_url_slug    = "talukas";
        $this->module_view_folder = "admin.talukas";
    }
 
    public function index ()
    {
        $this->arr_view_data['page_title']      = "Manage ".str_singular($this->module_title);
        $this->arr_view_data['module_title']    = str_plural($this->module_title);
        $this->arr_view_data['module_url_path'] = $this->module_url_path;

        return view($this->module_view_folder.'.index', $this->arr_view_data);
    }


    public function get_records()
    {
        $obj_data = $this->BaseModel->with(['country_details','state_details','district_details']);

        $json_result =  Datatables::of($obj_data)->make(true);
        
        /* Extract built JSON */
        $built_result = $json_result->getData();
        
        if(isset($built_result->data) && sizeof($built_result->data)>0)
        {
            
            foreach($built_result->data as $key => $data)
            {
                if($data->is_active==1)
                 {
                    $href = $this->module_url_path.'/deactivate/'.base64_encode($data->id);
                    $onclick = 'return confirm("Are you sure to Deactivate this record?")';
                    $built_is_active = "<a href='".$href."' class='btn btn-sm btn-success show-tooltip' title='Active' onclick='".$onclick."'><i class='fa fa-unlock'></i></a>"; 
                 }
                 else
                 {
                    $href = $this->module_url_path.'/activate/'.base64_encode($data->id);
                    $onclick = 'return confirm("Are you sure to Activate this record?")';
                    $built_is_active = "<a href='".$href."' class='btn btn-sm btn-danger show-tooltip' onclick='".$onclick."' title='In-Active'><i class='fa fa-lock'></i></a>";
                 }

                $built_result->data[$key]->built_is_active     = $built_is_active;

                $built_result->data[$key]->built_title         = isset($data->title)&&sizeof($data->title)>0?$data->title:'NA';
                $built_result->data[$key]->built_id            = base64_encode($data->id);

                $built_result->data[$key]->built_district      = isset($data->district_details->title)?$data->district_details->title:'NA';

                $built_result->data[$key]->built_state         = isset($data->state_details->title)?$data->state_details->title:'NA';

                $built_result->data[$key]->built_country       = isset($data->country_details->country_name)?$data->country_details->country_name:'NA';

                $built_result->data[$key]->built_edit_href     = $this->module_url_path.'/edit/'.base64_encode($data->id);
                $built_result->data[$key]->built_delete_href   = $this->module_url_path.'/delete/'.base64_encode($data->id);
                $built_result->data[$key]->confirmation_msg    = "return confirm('Do really want to delete this record?');";
            }
                return response()->json($built_result);
        }
        else
        {
            return $json_result;
        }
    }

    public function show($enc_id)
    {
        $id = base64_decode($enc_id);
        $arr_data = array();

        $obj_data = $this->BaseModel->where('id',$id)->with(['country_details','state_details'])->first();
        if( $obj_data != FALSE)
        {
            $arr_data = $obj_data->toArray();
        }



        $this->arr_view_data['arr_data'] = $arr_data;
        $this->arr_view_data['page_title'] = "Show ".str_singular($this->module_title);
        $this->arr_view_data['module_title'] = str_plural($this->module_title);
        $this->arr_view_data['module_url_path'] = $this->module_url_path;

        return view($this->module_view_folder.'.show', $this->arr_view_data);
    }

    
    public function create()
    {
        $arr_country = array();

        /* Build Country Module */
        $obj_country = $this->CountryModel->where('is_active',1)->get();

        if( $obj_country != FALSE)
        {
            $arr_country = $obj_country->toArray();
        }

        $arr_default = ['' => "Select Country"];

        $this->arr_view_data['arr_country'] = $this->build_select_options_array($arr_country,'id','country_name',$arr_default);

        /* Build State Module */
        $obj_state    = $this->StateModel->where('is_active',1)->get();
        
        if( $obj_state != FALSE)
        {
            $arr_state = $obj_state->toArray();
        }
       
        $this->arr_view_data['arr_state'] = $this->build_select_options_array($arr_state,'id','state_title',$arr_default);

        $this->arr_view_data['arr_lang'] = $this->LanguageService->get_all_language();  
        $this->arr_view_data['page_title'] = "Create ".str_singular($this->module_title);
        $this->arr_view_data['module_title'] = str_plural($this->module_title);
        $this->arr_view_data['module_url_path'] = $this->module_url_path;


        return view($this->module_view_folder.'.create', $this->arr_view_data);
    }


    public function store(Request $request)
    {  
        $obj_data   = Sentinel::getUser();
        $first_name = $obj_data->first_name;
        $last_name  = $obj_data->last_name;

        $form_data                    = array();
        $arr_rules['country_id']      = "required";
        $arr_rules['state_id']        = "required";
        $arr_rules['district_id']     = "required";
        $arr_rules['title_en']        = "required";
         
        $validator = Validator::make($request->all(),$arr_rules);
        if($validator->fails())
        {
            return redirect()->back()->withErrors($validator)->withInput();
        }
 
        $form_data = $request->all(); 

        $arr_data['district_id'] = $form_data['district_id']; 
        $arr_data['country_id']  = $form_data['country_id'];       
        $arr_data['state_id']    = $form_data['state_id'];

        $does_exists = $this->BaseModel->where('country_id', $request->input('country_id'))
                            ->where('state_id', $request->input('state_id'))
                            ->where('district_id', $request->input('district_id'))
                            ->whereHas('translations',function($query) use($request)
                            {
                                $query->where('locale', 'en')
                                      ->where('title',$request->input('title_en'));
                            })
                            ->count();

        if($does_exists)
        {
            Flash::error(str_singular($this->module_title).' Already Exists.');
            return redirect()->back();
        }
        else
        {
            $entity = $this->BaseModel->create($arr_data);
            if($entity)            
            {  
                $arr_lang =  $this->LanguageService->get_all_language();
                                                             
                if(sizeof($arr_lang) > 0 )
                {
                    foreach ($arr_lang as $lang) 
                    {            
                        $arr_data = array();

                        $taluka_title = $request->input('title_'.$lang['locale']);
                       

                        if( isset($taluka_title) && $taluka_title != "")
                        { 
                            $translation            = $entity->translateOrNew($lang['locale']);
                            $translation->taluka_id = $entity->id;
                            $translation->title     = $taluka_title;
                            $translation->save();

                            /*-------------------------------------------------------
                            |   Activity log Event
                            --------------------------------------------------------*/
                                $arr_event                 = [];
                                $arr_event['ACTION']       = 'ADD';
                                $arr_event['MODULE_TITLE'] = $this->module_title;

                                $this->save_activity($arr_event);
                            /*----------------------------------------------------------------------*/

                            Flash::success(str_singular($this->module_title).' Created Successfully');
                        }

                    }//foreach

                } //if
                else
                {
                    Flash::error('Problem Occured, While Creating '.str_singular($this->module_title));
                }

            }
            else
            {
                Flash::error('Problem Occured, While Creating '.str_singular($this->module_title));
            }
        }
       return redirect()->back();
    }

    public function edit($enc_id)
    {
        $id = base64_decode($enc_id);
        $arr_data = array();

        $obj_data = $this->BaseModel
                           ->where('id',$id)
                           ->with(['country_details','state_details','district_details','translations'])
                           ->first();

        if($obj_data)
        {
           $arr_data = $obj_data->toArray();
           /* Arrange Locale Wise */
           $arr_data['translations'] = $this->arrange_locale_wise($arr_data['translations']);
        }
       
        $arr_lang =  $this->LanguageService->get_all_language();


        $this->arr_view_data['edit_mode']       = TRUE;
        $this->arr_view_data['enc_id']          = $enc_id;
        $this->arr_view_data['arr_lang']        = $this->LanguageService->get_all_language();        
        $this->arr_view_data['arr_data']        = $arr_data;
        $this->arr_view_data['page_title']      = "Edit ".str_singular($this->module_title);
        $this->arr_view_data['module_title']    = str_plural($this->module_title);
        $this->arr_view_data['module_url_path'] = $this->module_url_path;

        return view($this->module_view_folder.'.edit', $this->arr_view_data);    
    }


    public function update(Request $request, $enc_id)
    {
        $obj_data   = Sentinel::getUser();
        $first_name = $obj_data->first_name;
        $last_name  = $obj_data->last_name;

        $id                    = base64_decode($enc_id);
        $arr_rules             = array();
        $arr_rules['title_en'] = "required"; 
        $validator             = Validator::make($request->all(),$arr_rules);

        if($validator->fails())
        {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $form_data = array();
        $form_data = $request->all();        
        $arr_data  = array();
        $arr_lang  = $this->LanguageService->get_all_language();  
        $entity    = $this->BaseModel->where('id',$id)->first();

        if(!$entity)
        {
            Flash::error('Problem Occured While Retriving '.str_singular($this->module_title));
            return redirect()->back();   
        }

        /* Check if category already exists with given translation */
        $does_exists = $this->BaseModel
                            ->where('id','<>',$id)
                            ->whereHas('translations',function($query) use($form_data)
                                        {
                                            $query->where('locale','en')
                                                  ->where('title',$form_data['title_en']);      
                                        })
                            ->count();   
        if($does_exists)
        {
            Flash::error(str_singular($this->module_title).' Already Exists');
            return redirect()->back();
        }

        if(sizeof($arr_lang) > 0)
        { 
            foreach($arr_lang as $i => $lang)
            {
                $taluka_title = $request->input('title_'.$lang['locale']);

                if( isset($taluka_title) && $taluka_title!="")
                {
                    /* Get Existing Language Entry */
                    $translation = $entity->getTranslation($lang['locale']);    

                    if($translation)
                    {
                        $translation->title =  $taluka_title;
                        $translation->save();    
                    }  
                    else
                    {
                        /* Create New Language Entry  */
                        $translation = $entity->getNewTranslation($lang['locale']);
                        
                        $translation->taluka_id    =  $id;
                        $translation->title        =  $taluka_title;
                        $translation->save();    
                    } 
                }   
            }
        }   

        /*-------------------------------------------------------
        |   Activity log Event
        --------------------------------------------------------*/
            $arr_event                 = [];
            $arr_event['ACTION']       = 'EDIT';
            $arr_event['MODULE_TITLE'] = $this->module_title;

            $this->save_activity($arr_event);
        /*----------------------------------------------------------------------*/

        Flash::success(str_singular($this->module_title).' Updated Successfully');
        return redirect()->back(); 
    }


    public function arrange_locale_wise(array $arr_data)
    {
        if(sizeof($arr_data)>0)
        {
            foreach ($arr_data as $key => $data) 
            {
                $arr_tmp = $data;
                unset($arr_data[$key]);

                $arr_data[$data['locale']] = $data;                    
            }

            return $arr_data;
        }
        else
        {
            return [];
        }
    }

    public function activate($enc_id = FALSE)
    {
        if(!$enc_id)
        {
            return redirect()->back();
        }

        if($this->perform_activate(base64_decode($enc_id)))
        {
            Flash::success(str_singular($this->module_title).' Activated Successfully');
        }
        else
        {
            Flash::error('Problem Occured While '.str_singular($this->module_title).' Activation ');
        }

        return redirect()->back();
    }

    public function deactivate($enc_id = FALSE)
    {
        if(!$enc_id)
        {
            return redirect()->back();
        }

        if($this->perform_deactivate(base64_decode($enc_id)))
        {
            Flash::success(str_singular($this->module_title).' Deactivated Successfully');
        }
        else
        {
            Flash::error('Problem Occured While '.str_singular($this->module_title).' Deactivation ');
        }

        return redirect()->back();
    }

    public function delete($enc_id = FALSE)
    {
        $obj_data   = Sentinel::getUser();
        $first_name = $obj_data->first_name;
        $last_name  = $obj_data->last_name;

        if(!$enc_id)
        {
            return redirect()->back();
        }

        if($this->perform_delete(base64_decode($enc_id)))
        {
            Flash::success(str_singular($this->module_title).' Deleted Successfully');
        }
        else
        {
            Flash::error('Problem Occured While '.str_singular($this->module_title).' Deletion ');
        }

        return redirect()->back();
    }

    public function multi_action(Request $request)
    {
        $obj_data   = Sentinel::getUser();
        $first_name = $obj_data->first_name;
        $last_name  = $obj_data->last_name;

        $arr_rules = array();
        $arr_rules['multi_action'] = "required";
        $arr_rules['checked_record'] = "required";


        $validator = Validator::make($request->all(),$arr_rules);

        if($validator->fails())
        {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $multi_action = $request->input('multi_action');
        $checked_record = $request->input('checked_record');

        /* Check if array is supplied*/
        if(is_array($checked_record) && sizeof($checked_record)<=0)
        {
            Session::flash('error','Problem Occured, While Doing Multi Action');
            return redirect()->back();

        }

        
        foreach ($checked_record as $key => $record_id) 
        {  
            if($multi_action=="delete")
            {
                
               $this->perform_delete(base64_decode($record_id));    
               Flash::success(str_plural($this->module_title).' Deleted Successfully'); 
            } 
            elseif($multi_action=="activate")
            {
               $this->perform_activate(base64_decode($record_id)); 
               Flash::success(str_plural($this->module_title).' Activated Successfully'); 
            }
            elseif($multi_action=="deactivate")
            {
               $this->perform_deactivate(base64_decode($record_id));    
               Flash::success(str_plural($this->module_title).' Blocked Successfully');  
            }
        }

        return redirect()->back();
    }

    public function perform_activate($id)
    {
        $entity = $this->BaseModel->where('id',$id)->first();

        if($entity)
        {
            return $entity->update(['is_active'=>1]);
        }

        return FALSE;
    }

    public function perform_deactivate($id)
    {
        $entity = $this->BaseModel->where('id',$id)->first();
        if($entity)
        {
            return $entity->update(['is_active'=>0]);
        }
        return FALSE;
    }    

    public function perform_delete($id)
    {
        $entity = $this->BaseModel->where('id',$id)->first();
        
        if($entity)
        {
            /*-------------------------------------------------------
            |   Activity log Event
            --------------------------------------------------------*/
                $arr_event                 = [];
                $arr_event['ACTION']       = 'REMOVED';
                $arr_event['MODULE_TITLE'] = $this->module_title;

                $this->save_activity($arr_event);
            /*----------------------------------------------------------------------*/
            return $entity->delete();
        }

        return FALSE;
    }

    public function build_select_options_array(array $arr_data,$option_key,$option_value,array $arr_default)
    {

        $arr_options = [];
        if(sizeof($arr_default)>0)
        {
            $arr_options =  $arr_default;   
        }

        if(sizeof($arr_data)>0)
        {
            foreach ($arr_data as $key => $data) 
            {
                if(isset($data[$option_key]) && isset($data[$option_value]))
                {
                    $arr_options[$data[$option_key]] = $data[$option_value];
                }
            }
        }
            return $arr_options;
    }
}
