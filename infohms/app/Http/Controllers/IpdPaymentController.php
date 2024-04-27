<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateIpdPaymentRequest;
use App\Http\Requests\UpdateIpdPaymentRequest;
use App\Models\IpdPayment;
use App\Queries\IpdPaymentDataTable;
use App\Repositories\IpdPaymentRepository;
use DataTables;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session as FacadesSession;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Laracasts\Flash\Flash;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use Illuminate\Support\Facades\Auth;

class IpdPaymentController extends AppBaseController
{
    /** @var IpdPaymentRepository */
    private $ipdPaymentRepository;

    public function __construct(IpdPaymentRepository $ipdPaymentRepo)
    {
        $this->ipdPaymentRepository = $ipdPaymentRepo;
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            return DataTables::of((new IpdPaymentDataTable())->get($request->id))->make(true);
        }
    }

    public function store(CreateIpdPaymentRequest $request)
    {
        $input = $request->all();

        if($input['payment_mode'] == IpdPayment::PAYMENT_MODES_STRIPE){

            $result = $this->ipdPaymentRepository->stripeSession($input);

            return $this->sendResponse([
                'ipdID' => $input['ipd_patient_department_id'],
                'payment_type' => $input['payment_mode'],
                $result
            ],'Stripe session created successfully');

        }elseif($input['payment_mode'] == IpdPayment::PAYMENT_MODES_RAZORPAY){

            return $this->sendResponse([
                'ipdID' => $input['ipd_patient_department_id'],
                'amount' => $input['amount'],
                'payment_type' => $input['payment_mode'],
            ],'Razorpay session created successfully');

        }else{
            $this->ipdPaymentRepository->store($input);
        }

        return $this->sendSuccess(__('messages.ipd_payment').' '.__('messages.common.saved_successfully'));
    }

    public function edit(IpdPayment $ipdPayment)
    {
        return $this->sendResponse($ipdPayment, 'IPD Payment retrieved successfully.');
    }

    public function update(IpdPayment $ipdPayment, UpdateIpdPaymentRequest $request)
    {
        $this->ipdPaymentRepository->updateIpdPayment($request->all(), $ipdPayment->id);

        return $this->sendSuccess(__('messages.ipd_payment').' '.__('messages.common.updated_successfully'));
    }

    public function destroy(IpdPayment $ipdPayment)
    {
        $this->ipdPaymentRepository->deleteIpdPayment($ipdPayment->id);

        return $this->sendSuccess(__('messages.ipd_payment').' '.__('messages.common.deleted_successfully'));
    }

    public function downloadMedia(IpdPayment $ipdPayment)
    {
        $media = $ipdPayment->getMedia(IpdPayment::IPD_PAYMENT_PATH)->first();

        if ($media != null) {
            $media = $media->id;
            $mediaItem = Media::find($media);

            return $mediaItem;
        }

        return '';
    }

    public function ipdStripePaymentSuccess(Request $request)
    {
        $this->ipdPaymentRepository->ipdStripePaymentSuccess($request->all());

        Flash::success(__('messages.payment.your_payment_is_successfully_completed'));

        if(getLoggedinPatient()){
            return redirect(route('patient.ipd'));
        }

        return redirect(route('ipd.patient.index'));
    }

    public function ipdRazorpayPayment(Request $request)
    {
        $result = $this->ipdPaymentRepository->razorpayPayment($request->all());

       return $this->sendResponse($result, 'order created');
    }

    public function ipdRazorpayPaymentSuccess(Request $request)
    {
        $this->ipdPaymentRepository->ipdRazorpayPaymentSuccess($request->all());

        Flash::success(__('messages.payment.your_payment_is_successfully_completed'));

        return redirect()->back();
    }
}
