<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Alert;
use Carbon\Carbon;
use App\Coupon;
use App\Model\Cart;
use App\CouponExport;

use Auth;
use Session;
use Excel;


class CouponController extends Controller
{

    //coupon index
    public function index()
    {
        return view('coupon.index');
    }

    //coupon index
    public function allCoupons(Request $request)
    {
        if ($request->get('search')) {
            $coupons = Coupon::where('code', $request->get('search'))->get();
            
            if(sizeof($coupons) == 0)
                $coupons = Coupon::where('id', $request->get('search'))->get();
        } else {
            $coupons = Coupon::latest()->get();
        }
        return view('coupon.list', compact('coupons'));
    }

    //store coupons
    public function store(Request $request)
    {
        // Generate random coupon code with 13 digits
        for($i = 0 ; $i < $request->vouchers; $i++){
            $coupon = new Coupon();
  
            $coupon->code = mt_rand(1,9) . mt_rand(0,9) . mt_rand(0,9) . mt_rand(0,9) . mt_rand(0,9). mt_rand(0,9). mt_rand(0,9). mt_rand(0,9). mt_rand(0,9). mt_rand(0,9). mt_rand(0,9). mt_rand(0,9). mt_rand(0,9);
            if ($request->is_published == 'on') {
                $coupon->is_published = true;
            } else {
                $coupon->is_published = false;
            }
            $coupon->group          = $request->group_name;  
            $coupon->start_day      = Carbon::parse($request->start_day);
            $coupon->end_day        = Carbon::parse($request->end_day);
            $coupon->min_value      = $request->min_value;
            $coupon->rate           = $request->rate;
            $coupon->is_used        = false;

            if ($request->is_published == 'on') {
                $coupon->is_published = true;
            } else {
                $coupon->is_published = false;
            }
            $coupon->save();
        }        

        Alert::success(translate('Done'), translate('Coupon Created Successfully'));
        return back();
    }

    //coupon edit
    public function edit($id)
    {
        $single_coupon = Coupon::findOrFail($id);
        return view('coupon.edit', compact('single_coupon'));
    }

    // Download Coupon by Group
    public function downloadGroup($id){
        return Excel::download(new CouponExport($id), 'coupons.xls');
    }


    // coupon_activation
    public function coupon_activation(Request $request)
    {
        $coupon_activation = Coupon::where('id', $request->id)->first();

        if ($coupon_activation->is_published == 0) {
            $coupon_activation->is_published = 1;
            $coupon_activation->save();
        } else {
            $coupon_activation->is_published = 0;
            $coupon_activation->save();
        }

        return response(['message' => 'Status changed'], 200);
    }

    //coupon update
    public function update(Request $request, $id)
    {
        $coupon_update = Coupon::findOrFail($id);
        $coupon_update->code = $request->code;
        $coupon_update->rate = $request->rate;
        $coupon_update->start_day = Carbon::parse($request->start_day);
        $coupon_update->end_day = Carbon::parse($request->end_day);
        $coupon_update->min_value = $request->min_value;

        if ($request->is_published == 'on') {
            $coupon_update->is_published = true;
        } else {
            $coupon_update->is_published = false;
        }

        if ($request->is_used == 'on') {
            $coupon_update->is_used = true;
        } else {
            $coupon_update->is_used = false;
        }

        $coupon_update->save();
        Alert::success(translate('Done'), translate('Coupon Updated Successfully'));
        return back();
    }


    /**
     * FRONTEND
     */

    public function coupon_store(Request $request)
    {
      $coupon = Coupon::where('code',$request->code)->Published()->select('code')->first();

      if ($coupon != null) {
          $start_day  = Carbon::create(Coupon::where('code',$request->code)->Published()->first()->start_day);
          $end_day    = Carbon::create(Coupon::where('code',$request->code)->Published()->first()->end_day);
          $min_value  = Coupon::where('code',$request->code)->Published()->first()->min_value;

         if (Carbon::now() > $start_day && Carbon::now() < $end_day) {
           if ($min_value <= $request->total) {
             session()->put('coupon',[
               'name' => $coupon->code,
               'discount' => $coupon->discount($coupon->rate),
               'total' => $request->total,
             ]);
              Session::flash('success', translate('Coupon applied'));
              return back();
           }else {
            Session::flash('error', 'Minimum Amount '. ' ' . $min_value . ' '  .'needed');
             return back();
           }
        }
        else {
            Session::flash('error',translate('Coupon expired.'));
          return back();
        }
      }else {
        Session::flash('error' ,translate('Invalid Coupon Code.'));
        return back();
      }
    }


    /**
     * COUPON DESTROY
     */

    public function coupon_destroy(Request $request)
    {
      session()->forget(['coupon']);
      Session::flash('error' ,translate('Coupon removed'));
      return back();
    }
}