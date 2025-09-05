<?php

namespace App\Http\Requests;

use Illuminate\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

class Recharge extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(Request $post)
    {
        $array = [
            'provider_id'      => 'required|numeric',
            'amount'      => 'required|numeric|min:10',
        ];

        if($post->has('type') && $post->type == "mobile"){
            $array = array_merge($array , ['number' => 'required|numeric|digits:10']);
        }else{
            $array = array_merge($array , ['number' => 'required|numeric|digits_between:8,15']);
        }

        return $array;
    }
}
