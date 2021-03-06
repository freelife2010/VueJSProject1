@extends('partials.modal')
@section('title')
    <em class="icon-plus"></em>&nbsp; {{$title}}
    <script type="text/javascript">
        $(document).ready(function() {
            var $did_action = $('#did_action');
            setModalWidth(400);
            bindActionSelectEvent($did_action);
            bindAppUserSelectEvent();
        });


        function bindActionSelectEvent($did_action) {
            $did_action.change(function() {
                getParameters($did_action);
                setTimeout(bindAppUserSelectEvent, 500);
            });
        }


        function bindAppUserSelectEvent() {
            var appUserSelect = $('#app_user_select');
            var sipUsersDiv = $('#sip_users');
            var ajaxCallback = function(data) {
                sipUsersDiv.html(data);
            };
            appUserSelect.change(function() {
                sipUsersDiv.html('Loading SIP Users...');
                var userId = appUserSelect.val();
                ajaxGetData('/app-users/sip-accounts-html/'+userId + '?app={{$APP->id}}', {}, ajaxCallback)
            });
        }

        function getParameters() {
            var $paramsDiv = $('#action_parameters');
            var ajaxCallback = function(data) {
                $paramsDiv.html(data);
            };
            var params = {
                'did_action': $('#did_action option:selected').val()
            };
            ajaxGetData('{{ url('did/parameters?app='.$APP->id) }}', params, ajaxCallback)

        }

        function ajaxGetData(url, params, success) {
            $.ajax({
                url: url,
                type: 'GET',
                data: params,
                success: success
            })
        }
    </script>
@stop
@section('modal_body')
    <?php
    use App\Models\AppUser;
    $action_url = url("did/edit/$model->id");
    Former::populate($model);
    $submit_label = 'Save';
    $edit = false;
    ?>
    <?= Former::vertical_open()->action($action_url) ?>
    <div style="margin-left: 15px">
        <?= Former::hidden('app_id')->value($APP->id);?>
        <?= Former::hidden('did')->value($model->did);?>
            <?= Former::select('owned_by')->options($appUsers, $model->owned_by)
                    ->label('APP User');?>
        <?= Former::select('did')->options(["$model->did"])->disabled();?>
        <?= Former::select('action')->id('did_action')->options($actions, $model->action_id);?>
        <div id="action_parameters">
            <?php
                if (!empty($params)) {
                    echo Former::label('Action parameter(s)');
                    $method = 'text';
                    foreach ($params as $param) {
                        $selectName = "parameters[$param->id]";
                        if ($param->name == 'Key-Action')
                            $method = 'textarea';
                        if (strpos($param->name, 'APP user id') !== false) {
                            $selectName = $model->action_id == 3 ? 'app_user_select' : $selectName;
                            $users = AppUser::whereAppId($APP->id)->lists('name', 'id');
                            echo Former::select($selectName)->options($users, $model->owned_by)
                                    ->placeholder($param->name)->label('');
                            echo $model->action_id == 3 ? '<div id="sip_users"></div>' : '';
                        } elseif (strpos($param->name, 'Conference') !== false) {
                            $conferences = \App\Models\Conference::whereAppId($APP->id)->lists('name', 'id');
                            echo Former::select($selectName)->options($conferences, $param->parameter_value)
                                    ->placeholder($param->name)->label('');
                        }
                        else echo Former::$method($selectName)->value($param->parameter_value)
                                ->help($param->name)->label('');
                    }
                }
            ?>
        </div>
    </div>
    <div style="clear: both"></div>
    <br/>
    <div class="pull-right">
        <?= Former::actions(
                Former::primary_button($submit_label)
                        ->type('submit')->setAttribute('data-submit', 'ajax')
                        ->class('btn btn-lg btn-info'),
                Former::button('Close')
                        ->setAttribute('data-dismiss', 'modal')->class('btn btn-lg btn-default')

        )?>
        <?= Former::close() ?>
    </div>
@stop
