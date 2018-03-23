<script language="php">
	header('Access-Control-Allow-Origin: *');
	//error_log(var_export($_GET, true));
	require_once('globais.php');
	if (in_array($_GET['ws'], array('login','acessa'))) {
		$string = "$base";
		if ($usuario != null){
		  $string.= " $usuario";
		}
		if ($senha != null){
		  $string.= " =$senha";
		}
		if ($servidor != null){
		  $string.= " =$servidor";
		}
		if ($porta != null){
		  $string.= " ";
		}

		$conexao = pg_pconnect('dbname='. DBNAME . ' user=' . DBUSER . ' password=' . DBPASS . ' host=' . DBHOST . ' port=5432');
		if (!$conexao) throw new Exception('Não foi possível conectar ao servidor');
	}

	function hashcpf ($cpf) {
		return md5('hash' . $cpf . DBNAME . 'md5');
	}
	
	function aguarda($cpf){
		$ip = $_SERVER['REMOTE_ADDR'];
		session_id(md5('REMOTE_ADDR' . $ip . 'REMOTE_ADDR'));
		session_start();
		if (!isset($_SESSION['CPF' . $cpf])) $_SESSION['CPF' . $cpf] = 0;
		$ts = (int)$_SESSION['CPF' . $cpf];
		$_SESSION['CPF' . $cpf]++;
		if ($ts > 15) $ts = 15;
		sleep($ts);
		session_write_close();
	}
	
	function libera($cpf){
		$ip = $_SERVER['REMOTE_ADDR'];
		session_id(md5('REMOTE_ADDR' . $ip . 'REMOTE_ADDR'));
		session_start();
		unset($_SESSION['CPF' . $cpf]);
		session_write_close();
	}
	
	//exit (hashcpf('04244266763'));
	
	function select($sql){
		global $conexao;
		$result = array();
		$q = pg_query($conexao,$sql); // or die ("Não foi possível executar a consulta SQL");
		if (!$q) throw new Exception('Não foi possível executar a consulta SQL'); 
		while ($row = pg_fetch_assoc($q))
			array_push($result,$row);	
		return $result;
	}
	
	if ($_GET['ws'] == 'login') {
		aguarda($_GET['cpf']);
		$pessoa = select('select pess_cpf as cpf, md5(pess_senha) as senha from vest_tb_pessoa where pess_cpf = \'' . addslashes($_GET['cpf']) . '\'');
		if (count($pessoa) != 1) die(json_encode(array('login' => false, 'mensagem' => 'CPF não encontrado')));
		if ($pessoa[0]['senha'] === md5($_GET['senha'])){
			libera($_GET['cpf']);
			die(json_encode(array('login' => true, 'mensagem' => hashcpf($_GET['cpf']))));
		} else {
			die(json_encode(array('login' => false, 'mensagem' => 'Senha inválida')));
		}
		
		
	} elseif ($_GET['ws'] == 'acessa') {
		$json = array();

		$url = 'http://www2.provaecomprova.com/vestcederj/arquivos/';
		if (DATA_ATUAL >= INICIO_REQUERIMENTO) 
			$json['arquivos']['edital_isencao'] = $url . 'edital_isencao.pdf';
		if (DATA_ATUAL >= INICIO_INSCRICAO) 
			$json['arquivos']['edital_vestibular'] = $url . 'edital_vestibular.pdf';
		if (DATA_ATUAL >= RESULTADO_REQUERIMENTO) 
			$json['arquivos']['resultado_isencao_cota'] = $url . 'resultado_isencao_cota.pdf';
		if (DATA_ATUAL >= RESULTADO_ENEM) 
			$json['arquivos']['resultado_enem'] = $url . 'resultado_enem.pdf';
		if (DATA_ATUAL >= RESULTADO_OBJETIVA) 
			$json['arquivos']['resultado_multipla_escolha'] = $url . 'resultado_multipla_escolha.pdf';
		if ((DATA_ATUAL >= PRELIMINAR_DISCREDA) && (DATA_ATUAL < RESULTADO_FINAL))
			$json['arquivos']['preliminar'] = $url . 'preliminar.pdf';
		if (DATA_ATUAL >= RESULTADO_FINAL) 
			$json['arquivos']['resultado'] = $url . 'resultado.pdf';
		if (DATA_ATUAL >= RESULT_MATRICULA0) 
			$json['arquivos']['primeira_reclassificacao'] = $url . 'primeira_reclassificacao.pdf';
		if (DATA_ATUAL >= RESULT_MATRICULA1) 
			$json['arquivos']['segunda_reclassificacao'] = $url . 'segunda_reclassificacao.pdf';
		if (DATA_ATUAL >= RESULT_MATRICULA2) 
			$json['arquivos']['terceira_reclassificacao'] = $url . 'terceira_reclassificacao.pdf';


		if (hashcpf($_GET['cpf']) !== $_GET['senha']) {
			die(json_encode($json));
		}
		
		
		$pessoa = select('select pess_cpf as cpf, toup(pess_nome) as nome from vest_tb_pessoa where pess_cpf = \'' . addslashes($_GET['cpf']) . '\'');
		if (count($pessoa) != 1) die(json_encode($json));
		$json['pessoa'] = $pessoa[0];
		
		$requerimento = select("select 
			requ_statusisencao
			, case when r.requ_tiporequerimento in ('ISEN','ISCT') then 'Sim' else 'Não' end as isencao
			, r.aval_parecer_isencao_final as isencao2
			, r.aval_parecer_isencao_texto as isencao3
			, coalesce(v.isencao, r.aval_parecer_isencao_final) as isencao4
			, case r.requ_tipocota
				when 'EPBL' then 'Estudantes de escolas públicas'
				when 'NEGR' then 'Negros'
				when 'DEFI' then 'Deficientes'
				when 'FILH' then 'Filhos de policiais mortos em serviço'
				when 'MINO' then 'Minorias'
				end as cota
			, r.aval_parecer_cota_final as cota2
			, r.aval_parecer_cota_texto as cota3
			, coalesce(v.cota, r.aval_parecer_cota_final) as cota4
			, case r.requ_afirmativa
				when 'S' then 'Sim'
				when 'N' then 'Não'
			  end as afirmativa
			, r.aval_parecer_afirmativa_final as afirmativa2
			, r.aval_parecer_afirmativa_texto as afirmativa3
			, coalesce(v.afirmativa, r.aval_parecer_afirmativa_final) as afirmativa4
			, case r.requ_renda
				when 'P' then 'Sim'
				when 'G' then 'Não'
			  end as renda
			, r.aval_parecer_renda_final as renda2
			, r.aval_parecer_renda_texto as renda3
			, coalesce(v.renda, r.aval_parecer_renda_final) as renda4
			, case r.requ_etnia
				when 'N' then 'Negros'
				when 'P' then 'Pardos'
				when 'I' then 'Índios'
				when 'O' then 'Brancos/Outros'
			  end as etnia
			, r.aval_parecer_etnia_final as etnia2
			, r.aval_parecer_etnia_texto as etnia3
			, coalesce(v.etnia, r.aval_parecer_etnia_final) as etnia4
			, case r.requ_forpro
				when 'S' then 'Sim'
				when 'N' then 'Não'
			  end as forpro
			, r.aval_parecer_forpro_final as forpro2
			, r.aval_parecer_forpro_texto as forpro3
			, coalesce(v.forpro, r.aval_parecer_forpro_final) as forpro4
			from vest_tb_requerimento r
			left join vest_tb_requerimento_recurso v on v.requ_numero = r.requ_numero
			where pess_cpf = '" . addslashes($_GET['cpf']) . "'
		");
		if (count($requerimento) == 1) {
			$requerimento = $requerimento[0];
			$json['requerimento'] = array();
			
			if (DATA_ATUAL >= RESULTADO_REQUERIMENTO) {
				if ($requerimento['requ_statusisencao'] == null) {
					$json['requerimento']['situacao'] = 'CANCELADO: NÃO ENVIOU A DOCUMENTAÇÃO';
				} else {
					foreach (array('isencao','cota','afirmativa','renda','etnia','forpro') as $k) {
						if (DATA_ATUAL >= DIVULGACAO_REQUERIMENTO) {
							$json['requerimento'][$k] = array($requerimento[$k]);
							$json['requerimento'][$k][] = $requerimento[$k . '4'];
							$json['requerimento'][$k][] = $requerimento[$k . '3'];
						} elseif (DATA_ATUAL >= RESULTADO_REQUERIMENTO) {
							$json['requerimento'][$k] = array($requerimento[$k]);
							$json['requerimento'][$k][] = $requerimento[$k . '2'];
							$json['requerimento'][$k][] = $requerimento[$k . '3'];
						} else {
							$json['requerimento'][$k] = $requerimento[$k];
						}
					}
				}
			} else {
				$json['requerimento']['situacao'] = 'AINDA NÃO AVALIADO';
			}
			
		}
		
		$inscricao = select("
			select to_char(i.insc_numero, '0000000') as numero
			, coalesce(e.enem_status_inscricao,i.insc_status) as statuse
			, i.insc_status as statusi
			, c.curs_nome as curso
			, p.polo_nome as polo
			, v.inst_cv_sigla as instituicao
			from vest_tb_inscricao i
			inner join vest_tb_curso c on c.curs_cv_codigo = i.curs_cv_codigo
			inner join vest_tb_polo p on p.polo_cv_sigla = i.polo_cv_sigla
			inner join vest_tb_participa_polo_aber v on v.curs_cv_codigo = i.curs_cv_codigo and v.polo_cv_sigla = i.polo_cv_sigla
			left join vest_tb_enem e on e.insc_numero = i.insc_numero
			where i.pess_cpf = '" . addslashes($_GET['cpf']) . "'
			order by i.insc_numero
		");
		
		$status = array(
			'PDPG' => 'Inscrição não paga',
			'CANC' => 'Inscrição Cancelada',
			'PAGO' => 'Inscrição paga',
			'ISEN' => 'Inscrição isenta de pagamento',
			'NCLS' => 'Não classificado pelo ENEM',
			'ENEM' => 'Classificado pelo ENEM',
			'PGME' => 'Pagamento realizado com valor menor',
		);
		
		
		foreach (array_keys($inscricao) as $k) {
			if (DATA_ATUAL >= RESULTADO_ENEM) {
				$inscricao[$k]['status'] = $status[$inscricao[$k]['statusi']];
			} else {
				$inscricao[$k]['status'] = $status[$inscricao[$k]['statuse']];
			}
			unset($inscricao[$k]['statuse']);
			unset($inscricao[$k]['statusi']);
		}
		
		if (count($inscricao) > 0)
			$json['inscricao'] = $inscricao;


		if (DATA_ATUAL >= RESULTADO_ENEM) {
			$enem = select("
				select e.enem_media as media,
				e.enem_redacao as redacao,
				e.enem_linguagem as linguagens,
				e.enem_matematica as matematica,
				e.enem_humanas as humanas,
				e.enem_natureza as natureza,
				e.enem_classificacao as classificacao,
				e.enem_status as situacao,
				e.enem_descvaga as vaga
				from vest_tb_inscricao i
				inner join vest_tb_enem e on e.insc_numero = i.insc_numero
				where i.pess_cpf = '" . addslashes($_GET['cpf']) . "'");
		
			if (count($enem) == 1) {
				if ($enem[0]['vaga'] == '') unset($enem[0]['vaga']);
				$json['enem'] = $enem[0];
			}
		}
		
		if (DATA_ATUAL >= INICIO_ALOCACAO) {
			$prova = select("
				select a.aloc_escola as escola,
				a.aloc_sala as sala,
				a.aloc_endereco as endereco,
				a.aloc_bairro as bairro,
				a.aloc_cidade as cidade,
				a.aloc_uf as estado,
				a.aloc_referencia as referencia
				from vest_tb_inscricao i
				inner join vest_tb_alocacao a on a.insc_numero = i.insc_numero
				where i.pess_cpf = '" . addslashes($_GET['cpf']) . "'");
			if (count($prova) == 1)
				$json['prova'] = $prova[0];
		}
		if (DATA_ATUAL >= RESULTADO_OBJETIVA) {
			$objetiva = select ("
				select o.obje_objetiva as nota,
				o.obje_acertos as acertos,
				o.obje_status as situacao
				from vest_tb_inscricao i
				inner join vest_tb_objetiva o on o.insc_numero = i.insc_numero
				where i.pess_cpf = '" . addslashes($_GET['cpf']) . "'");
			if (count($objetiva) == 1)
				$json['objetiva'] = $objetiva[0];
		}
		if (DATA_ATUAL >= RESULTADO_FINAL) {
			$resultado = select ("
				select r.resu_objetiva as nota_objetiva,
				r.resu_redacao as nota_redacao,
				r.resu_notafinal as nota_final,
				r.resu_classificacao as classificacao,
				case when r.resu_status = 'RECLASSIFICADO' then 'NÃO CLASSIFICADO' else r.resu_status end as situacao
				from vest_tb_inscricao i
				inner join vest_tb_resultado r on r.insc_numero = i.insc_numero
				where i.pess_cpf = '" . addslashes($_GET['cpf']) . "'");
			if (count($resultado) == 1)
				$json['resultado_final'] = $resultado[0];
		}
		
		$resultados = array(RESULTADO_FINAL, RESULT_MATRICULA0, RESULT_MATRICULA1, RESULT_MATRICULA2, RESULT_MATRICULA3);
			
		$matricula = select ("
			select m.matr_reclassificacao as reclassificacao,
			m.matr_status as situacao,
			d.disc_codigo as codigo,
			d.disc_nome as disciplina,
			case when m.polo_cv_sigla <> i.polo_cv_sigla then p.polo_nome end as polo
			from vest_tb_inscricao i
			inner join vest_tb_matricula m on m.insc_numero = i.insc_numero
			inner join vest_tb_polo p on p.polo_cv_sigla = m.polo_cv_sigla
			inner join vest_tb_matricula_disciplina md on md.insc_numero = m.insc_numero
			inner join vest_tb_disciplina d on d.disc_codigo = md.disc_codigo
			where i.pess_cpf = '" . addslashes($_GET['cpf']) . "' order by d.disc_codigo");
		
		if ((count($matricula) > 0) && (DATA_ATUAL >= $resultados[(int)$matricula[0]['reclassificacao']])) {
			$json['matricula'] = array();
			if ((int)$matricula[0]['reclassificacao'] > 0)
				$json['matricula']['reclassificacao'] = 'RECLASSIFICADO';
			if ($matricula[0]['polo'] != null)
				$json['matricula']['polo'] = $matricula[0]['polo'];
			$reclas = (int)$matricula[0]['reclassificacao'];
			$reclas++;
			if (DATA_ATUAL >= $resultados[$reclas]) {
				$json['matricula']['situacao'] = $matricula[0]['situacao'];
			}
			
				//'situacao' => ((DATA_ATUAL >= $resultados[(int)$matricula[0]['reclassificacao'] + 1]) ? (int)$matricula[0]['situacao'] : 'CONVOCADO'),
			
			foreach ($matricula as $k => $v) $json['matricula']['disc' . $k] = array($v['codigo'] , $v['disciplina']);
		}
		
		
		
		die(json_encode($json));
	} else {
		throw new Exception('Operação não encontrada!'); 
	}
</script>