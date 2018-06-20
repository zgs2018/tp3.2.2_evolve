<?php
namespace Home\Controller;

use Common\Controller\BaseController;
use Home\Model\CourseMdoel;
use Home\Model\LetterModel;
use Home\Model\PeriodModel;
use Home\Model\ProfileModel;
use Home\Model\StudentModel;
use Think\Exception;

class StudentController extends BaseController
{
    public function index ()
    {
        $s_id           =   session('_student')['id'];
        // TODO 购买产品、课程列表、课表信息
        $schedule       =   [];
        $course         =   [];
        $product        =   [];
        $studentModel           =   new StudentModel();
        $periodModel            =   new PeriodModel();
        $letterModel            =   new LetterModel();
        $scheduleData           =   $studentModel->studentSchedule($s_id);
        $now                    =   time();
        foreach ($scheduleData as $key => $value){
            // 排课状态  是否已经结束
            $value['status']        =   ($now>$value['stamp']) ? -1 : 1;
            $schedule[date('Y.m.d',$value['stamp'])][]    =   $value;
        }
        // 课程
        // 班级
        $period                 =   $periodModel->period_list(['s.id'=>['eq',$s_id]]);
        $period                 =   array_map( function($p){
            $p['course_pic']        =   'http://192.168.0.160:8087'.substr( $p['course_pic'],1 );
            return $p;
        },$period );
        // 排课

        // 学生信息
        $studentInfo            =   $studentModel->field('password,remark',true)->relation('profile')->find($s_id);
        // 未读信息数量
        $letterUnreadCount      =   $letterModel->unreadCount(['student_id'=>['eq',$s_id]]);


        $this->ajaxReturn( [
            'result'                    =>  true,
            'schedule'                  =>  $schedule,
            'period'                    =>  $period,
            'letterUnreadCount'         =>  $letterUnreadCount,
            'info'                      =>  $studentInfo,
        ] );
    }

    public function profile ()
    {
        $model          =   new StudentModel();

        $info           =   $model->field('mobile,password',true)->relation('profile')->find(session('_student.id'));

        $this->ajaxReturn([
            'result'    =>  true,
            'info'      =>  $info
        ]);
    }

    public function setting ()
    {
        try{
            if( !IS_POST || IS_AJAX ){
                $params             =   I('get.');
                $s_id               =   (int)session('_student.id');
                $profileModel       =   new ProfileModel();
                C('TOKEN_ON',false);
                if( $data=$profileModel->field('nickname,address,bind_mobile,sex')->create($params,1) ){
                    // 开启事务
                    $profileModel->startTrans();
                    $data['student_id']     =   $s_id;
                    if( $profileModel->find($s_id) ){
                        $profileModel->save($data)===false && E($profileModel->getError());
                    }else{
                        $profileModel->add($data)===false && E($profileModel->getError());
                    }
                    // 提交
                    $profileModel->commit();
                    $this->ajaxReturn(['result'=>true]);
                }
                E( $profileModel->getError() );
            }
            E('非法请求');
        }catch (Exception $e){
            $profileModel->rollback();
            $this->ajaxReturn( [
                'result'            =>  false,
                'error'             =>  $e->getMessage()
            ] );
        }
    }

    public function uploadHeadpic ($fileKey='file')
    {
        try{
            if( $_FILES[$fileKey] && $_FILES[$fileKey]['error']==0 ){
                $info=$this->uploadOne( $fileKey, 'headpic/' );
                $info===false && E($this->error);
                $data['headpic']        =   $info['savepath'].$info['savename'];
                $data['student_id']     =   (int)session('_student.id');
                $model                  =   new ProfileModel();
                if( $model->find($data['student_id']) ){
                    $model->where(['student_id'=>['eq',$data['student_id']]])->setField('headpic',$data['headpic'])===false && E($model->getError());
                }else{
                    $model->add($data)===false && E($model->getError());
                }

                $this->ajaxReturn( ['result'=>true] );
            }
            E('图片信息有误');
        }catch (Exception $e){
            $this->ajaxReturn([
                'result'        =>  false,
                'error'         =>  $e->getMessage(),
            ]);
        }
    }
}