<?php

namespace App\Http\Controllers\Emkt;

use App\Acao;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Emkt\ListaController;
use App\Http\Controllers\PlanilhaController;
use App\Instituicao;
use App\Mensagem;
use Session;

class AcaoController extends Controller
{
    public function aknaAPI()
    {
        return new AknaController;
    }

    public function planilha()
    {
        return new PlanilhaController;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        
        return view('admin.emkt.acoes.index', ['acoes' => Acao::all()]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $tipos_de_acoes = [ 
            1 => 'Ausentes',
            2 => 'Inscritos Parciais',
            3 => 'Lembrete de Prova'
        ];

        return view('admin.emkt.acoes.create', ['instituicoes' => Instituicao::all(), 'tipos_de_acoes' => $tipos_de_acoes]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $date = date('d-m-Y', strtotime($request->input('date')));
        $tipo_de_acao = $request->input('tipo_de_acao');
        $subject = $tipo_de_acao;
        $date = str_replace('-', '/', $date);
        $titulo_da_acao = $request->input('titulo').' '.$date;
        $agendamento_envio = $request->input('envio');
        $data_agendamento =  date('Y-m-d', strtotime($request->input('data_agendamento')));
        $hora_agendamento = $request->input('hora_agendamento');
        $agendamento_envio = $data_agendamento.' '.$hora_agendamento.':00';
        $extension = 'csv';
        $hasAction = true;
        $hasList = $request->input('hasList');

        if($hasList == 'importar-agora')
        {
            if($request->hasFile('import_file'))
            {
                $currentFile = $this->planilha()->load($request->file('import_file')->getRealPath());
            }
    
            $nomes_das_listas = (new ListaController())->import($currentFile, $extension, $subject, $date, $hasAction);
        } else {
            $nomes_das_listas = null;
        }

        $instituicoes = Instituicao::all();
        $instituicoes_selecionadas = [];

        foreach ($instituicoes as $instituicao)
        {
            $mensagem = Mensagem::all()
                ->where('tipo_de_acao', '=', $tipo_de_acao)
                ->where('instituicao_id', '=', $instituicao->id)
                ->first();
                
            if(!is_null($request->input('instituicao-'.strtolower($instituicao->prefixo))))
            {
                $status = $this->aknaAPI()->criarAcaoPontual($titulo_da_acao, $mensagem, $agendamento_envio, $instituicao, $nomes_das_listas);
                    
                if($status != 'Já existe uma campanha cadastrada com esse título!')
                {
                    $acao = new Acao;
                    $acao->titulo = $titulo_da_acao;
                    $acao->envio = $agendamento_envio;
                    $acao->destinatarios = 0;
                    $acao->status = $status;
                    $acao->agendamento = $agendamento_envio;
                    $acao->usuario = Auth::user()->id;
                    $acao->mensagem_id = $mensagem->id;
                    $acao->save();

                } else {
                    return back()->with('danger', 'Já existe uma campanha cadastrada com esse título!');
                }
                    
                Session::flash('message-'.$instituicao->prefixo, $status);
            }
        }

        return back()->with('success', 'Ação criada com sucesso!');
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Acao  $acao
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        return view('admin.emkt.acao.show', ['acao' => Acao::findOrFail($id)]);
    }

}
