<?php

namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Http\Requests;
use App\Models\CityModel;
use App\Models\CountryModel;
use App\Models\StateModel;
use Illuminate\Http\Request;
use Session;
use Validator;
use App\Common\Services\LanguageService;
use Flash;
 
class CityController extends Controller
{

     /*
    | Constructor : creates instances of model class 
    |               & handles the admin authantication
    | auther :Paras Kale
    | Date : 05/11/2016
    | @return \Illuminate\Http\Response
    */

    public function __construct(CountryModel $countries,
                                StateModel $state,
                                CityModel $city,
                                LanguageService $langauge
                                )
    {
        $this->CountryModel       = $countries;
        $this->StateModel         = $state;
        $this->CityModel          = $city;
        $this->LanguageService    = $langauge;
        
        $this->BaseModel          = $this->CityModel;

        $this->arr_view_data      = [];
        $this->module_url_path    = url(config('app.project.admin_panel_slug')."/cities");
        $this->module_title       = "Cities";
        $this->module_url_slug    = "cities";
        $this->module_view_folder = "admin.cities";
        $this->theme_color        = theme_color();
    }


     /*
    | Index : Display listing of countries
    | auther : Paras Kale 
    | Date : 05/11/2016
    | @return \Illuminate\Http\Response
    */ 
 
    public function index ()
    {
        $this->arr_view_data['arr_data'] = array();
        $obj_data = $this->BaseModel->with(['country_details','state_details'])->get();
       
        $arr_lang =  $this->LanguageService->get_all_language();

        if(sizeof($obj_data)>0)
        {
            foreach ($obj_data as $key => $data) 
            {
                $arr_tmp = array();
                /* Check Language Wise Transalation Exists*/
                foreach ($arr_lang as $key => $lang) 
                {
                    
                    $arr_tmp[$key]['title'] = $lang['title'];
                    $arr_tmp[$key]['is_avail'] = $data->hasTranslation($lang['locale']);
                }    

                $data->arr_translation_status = $arr_tmp;

                /* Call to hasTranslation method of object is triggering translations so need to unset it */
                unset($data->translations);
            }   

            $this->arr_view_data['arr_data'] = $obj_data->toArray();
        }


        $this->arr_view_data['page_title']      = "Manage ".str_singular($this->module_title);
        $this->arr_view_data['module_title']    = str_plural($this->module_title);
        $this->arr_view_data['module_url_path'] = $this->module_url_path;
        $this->arr_view_data['arr_lang']        = $arr_lang;
        $this->arr_view_data['theme_color']     = $this->theme_color;

        return view($this->module_view_folder.'.index', $this->arr_view_data);
    }


    /*
    | Show() : Display detail information regarding specific cities
    | auther : Paras Kale
    | Date   : 05/11/2016
    | @param  int  $enc_id
    | @return \Illuminate\Http\Response
    */

    public function show($enc_id)
    {
        $id = base64_decode($enc_id);
        $arr_data = array();

        $obj_data = $this->BaseModel->where('id',$id)->with(['country_details','state_details'])->first();
        if( $obj_data != FALSE)
        {
            $arr_data = $obj_data->toArray();
        }



        $this->arr_view_data['arr_data']        = $arr_data;
        $this->arr_view_data['page_title']      = "Show ".str_singular($this->module_title);
        $this->arr_view_data['module_title']    = str_plural($this->module_title);
        $this->arr_view_data['module_url_path'] = $this->module_url_path;
        $this->arr_view_data['theme_color']     = $this->theme_color;

        return view($this->module_view_folder.'.show', $this->arr_view_data);
    }


    /*
    | create() : Show the form for creating a new resource.
    | auther : Paras Kale 
    | Date : 05/11/2016    
    | @param  \Illuminate\Http\Request  $request
    */
    
    public function create()
    {
        $arr_country = array();

        /* Build Country Module */
        $obj_country = $this->CountryModel->where('is_active',1)->get();

        if( $obj_country != FALSE)
        {
            $arr_country = $obj_country->toArray();
        }

        $arr_default = [0 => "Select"];

        $this->arr_view_data['arr_country'] = $this->build_select_options_array($arr_country,'id','country_name',$arr_default);

        /* Build State Module */
        $obj_state    = $this->StateModel->where('is_active',1)->get();
        if( $obj_state != FALSE)
        {
            $arr_state = $obj_state->toArray();
        }
       
        $this->arr_view_data['arr_state'] = $this->build_select_options_array($arr_state,'id','state_title',$arr_default);

        $this->arr_view_data['arr_lang']        = $this->LanguageService->get_all_language();
        $this->arr_view_data['page_title']      = "Create ".str_singular($this->module_title);
        $this->arr_view_data['module_title']    = str_plural($this->module_title);
        $this->arr_view_data['module_url_path'] = $this->module_url_path;
        $this->arr_view_data['theme_color']     = $this->theme_color;


        return view($this->module_view_folder.'.create', $this->arr_view_data);
    }
  


    /*
    | store() : Stores newly created coutry.
    | auther : Paras Kale 
    | Date : 05/11/2016
    | @param  \Illuminate\Http\Request  $request
    | @return \Illuminate\Http\Response
    */

    public function store(Request $request)
    {  
        $form_data                  = array();
        $arr_rules['country_id']    = "required";
        $arr_rules['state_id']      = "required";
        $arr_rules['city_title_en'] = "required";
         
        $validator = Validator::make($request->all(),$arr_rules);
        if($validator->fails())
        {
            return redirect()->back()->withErrors($validator)->withInput();
        }
 
        $form_data = $request->all(); 

        $arr_data['public_key'] = str_random(7); 
        $arr_data['country_id'] = $request->input('country_id');       
        $arr_data['state_id']   = $request->input('state_id');

        $does_exists = $this->BaseModel->where('country_id', $request->input('country_id'))
                            ->where('state_id', $request->input('state_id'))
                            ->whereHas('translations',function($query) use($request)
                            {
                                $query->where('locale', 'en')
                                      ->where('city_title',$request->input('city_title_en'));
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

                        $city_title = $request->input('city_title_'.$lang['locale']);
                       

                        if( isset($city_title) && $city_title != "")
                        { 
                            $translation = $entity->translateOrNew($lang['locale']);
                            $translation->city_id     = $entity->id;
                            $translation->city_title  = $city_title;
                            $translation->city_slug   = str_slug($city_title, "-");
                            $translation->save();

                            Flash::success(str_singular($this->module_title).' Created Successfully');
                        }

                    }//foreach

                } //if
                else
                {
                    Flash::error('Problem Occurred, While Creating '.str_singular($this->module_title));
                }

            }
            else
            {
                Flash::error('Problem Occurred, While Creating '.str_singular($this->module_title));
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
                           ->with(['country_details','state_details','translations'])
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
        $this->arr_view_data['theme_color']     = $this->theme_color;

        return view($this->module_view_folder.'.edit', $this->arr_view_data);    
    }


    /*
    | update() : Update the specified resource/record
    | auther : Paras Kale
    | Date : 05/11/2016
    | @param  int  $enc_id
    | @return \Illuminate\Http\Response
    */

    public function update(Request $request, $enc_id)
    {
        $id = base64_decode($enc_id);
        $arr_rules = array();
        $arr_rules['city_title_en'] = "required";
        
         
        $validator = Validator::make($request->all(),$arr_rules);
        if($validator->fails())
        {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $form_data = array();
        $form_data = $request->all();    
        
        $arr_data = array();

        $arr_lang = $this->LanguageService->get_all_language();  
  
        $entity = $this->BaseModel->where('id',$id)->first();

        if(!$entity)
        {
            Flash::error('Problem Occured While Retriving '.str_singular($this->module_title));
            return redirect()->back();   
        }

        /* Check if category already exists with given translation */
        $does_exists = $this->BaseModel
                            ->where('id','<>',$id)
                            ->whereHas('translations',function($query) use($request)
                                        {
                                            $query->where('locale','en')
                                                  ->where('city_title',$request->input('city_title_en'));      
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
                $city_title = $request->input('city_title_'.$lang['locale']);

                if( isset($city_title) && $city_title!="")
                {
                    /* Get Existing Language Entry */
                    $translation = $entity->getTranslation($lang['locale']);    

                    if($translation)
                    {
                        $translation->city_title =  $city_title;
                        $translation->city_slug   = str_slug($city_title, "-");
                        $translation->save();    
                    }  
                    else
                    {
                        /* Create New Language Entry  */
                        $translation = $entity->getNewTranslation($lang['locale']);
                        
                        $translation->city_id    =  $id;
                        $translation->city_title =  $city_title;
                        $translation->city_slug  =  str_slug($city_title, "-");
                        $translation->save();    
                    } 
                }   
            }
        }    

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
    
    /*
    | active() : Change record status to deactive/inactive/block
    | auther : Paras Kale 
    | Date : 05/11/2016
    | @param  int  $enc_id
    | @return \Illuminate\Http\Response
    */


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

    

    /*
    | deactivate() : Change record status to deactive/inactive/block
    | auther : Paras Kale 
    | Date : 05/11/2016
    | @param  int  $enc_id
    | @return \Illuminate\Http\Response
    */

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

    /*
    | delete() : Delete State record
    | auther : Paras Kale 
    | Date : 05/11/2016
    | @param  int  $enc_id
    | @return \Illuminate\Http\Response
    */

    public function delete($enc_id = FALSE)
    {
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

     /*
    | multi_action() : mutiple actions like active/deactive/delete for multiple slected records
    | auther : Paras Kale 
    | Date : 05/11/2016    
    | @param  \Illuminate\Http\Request  $request
    */
    public function multi_action(Request $request)
    {
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
