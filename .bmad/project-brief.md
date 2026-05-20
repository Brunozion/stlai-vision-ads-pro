Não altere nenhum outro arquivo.
Não altere PHP, JS, CSS, imagens, assets ou configurações.
Não implemente nada ainda.

Substitua todo o conteúdo atual de .bmad/project-brief.md pelo conteúdo abaixo:

# Project Brief — STLAI Vision Ads Pro

## Visão geral

STLAI Vision Ads Pro é um plugin WordPress/PHP para criação de anúncios de marketplace com inteligência artificial.

O foco principal do plugin é ajudar usuários a transformar imagens de produtos, especialmente produtos físicos e produtos fabricados por impressão 3D, em anúncios completos e otimizados para marketplace.

O plugin guia o usuário em um fluxo visual de 6 etapas:

1. Upload das imagens do produto.
2. Leitura visual com IA e configuração do contexto do anúncio.
3. Geração e aprovação de títulos e descrição.
4. Geração de imagens comerciais do produto.
5. Configuração e geração de vídeo.
6. Resumo e exportação dos ativos gerados.

## Estado atual do projeto

O plugin já possui o fluxo principal funcionando sem a geração real de vídeo.

Funcionalidades existentes:

- Upload de imagens do produto.
- Leitura da imagem por IA.
- Extração automática de contexto do produto.
- Preenchimento automático de nome, descrição, material, cor, peso, voltagem e características.
- Escolha de plano Básico ou Premium.
- Geração de títulos para marketplace.
- Geração de descrição otimizada.
- Aprovação manual dos textos.
- Geração de imagens comerciais.
- Seleção de imagens para vídeo.
- Tela visual do passo 5 já existente como placeholder de vídeo.
- Tela final de resumo e exportação.
- Painel administrativo para configurar APIs, modelos e prompts.
- Análise competitiva/marketplace em área própria.

## Objetivo atual

Implementar a etapa de vídeo do plugin sem quebrar o fluxo atual.

A etapa de vídeo inicial deve usar:

- Veo 3.1 Flash para gerar clipes de vídeo comerciais a partir das imagens selecionadas.
- ElevenLabs para gerar narração.
- Um pipeline interno para montar um vídeo final narrado.

A arquitetura deve ser preparada para suportar providers futuros de vídeo e UGC sem reescrever o plugin.

Providers previstos:

- Veo 3.1 Flash: provider principal para vídeos comerciais do produto.
- ElevenLabs: provider principal para narração.
- Seedance 2.0 / BytePlus ModelArk: provider futuro para vídeos UGC.
- MuAPI: provider opcional para testes, comparação e validação de APIs terceiras.
- API direta do fornecedor UGC: provider preferencial no futuro, evitando dependência de terceiros quando possível.

## Pipeline de vídeo comercial desejado

1. No passo 2, o usuário escolhe o tipo de narração.

Tipos iniciais de narração:

- emocional
- persuasiva

2. No passo 4, o usuário seleciona de 4 a 8 imagens geradas.

3. No passo 5, o sistema gera 4 vídeos diferentes usando Veo 3.1 Flash.

4. Cada vídeo deve ter 8 segundos.

5. Os 4 vídeos devem representar ângulos comerciais diferentes do mesmo produto.

Exemplos de ângulos comerciais:

- apresentação geral do produto
- destaque de benefício
- demonstração de uso
- cena final premium/hero

6. O sistema gera uma narração com ElevenLabs com base em:

- nome do produto
- descrição/contexto
- características
- títulos aprovados
- tipo de narração escolhido
- idioma selecionado

7. O sistema monta um vídeo final:

- usando os 4 vídeos em sequência
- repetindo os clipes se o áudio for maior que 32 segundos
- aplicando fade ou transição suave entre clipes
- cortando no tempo final exato da narração
- sincronizando o vídeo final com a duração total do áudio

8. O usuário deve conseguir:

- visualizar o vídeo final
- baixar o vídeo final
- avançar para o resumo
- encontrar o vídeo também no passo 6

## Roadmap futuro de UGC

Além dos vídeos comerciais automáticos, o plugin deverá futuramente oferecer uma opção de UGC.

A opção UGC deve permitir criar vídeos com linguagem mais natural, estilo criador de conteúdo, anúncio demonstrativo, review curto ou apresentação informal do produto.

A implementação inicial de vídeo comercial não deve bloquear esse roadmap.

Por isso, a arquitetura deve separar:

- geração de clipes comerciais
- geração de narração
- composição final
- geração UGC
- providers externos de vídeo
- providers externos de áudio

O objetivo é permitir trocar ou adicionar APIs sem alterar o fluxo principal do plugin.

Possíveis formatos futuros de UGC:

- unboxing do produto
- demonstração de uso
- review curto
- comparação
- apresentação de benefício
- vídeo estilo criador de conteúdo
- vídeo estilo anúncio nativo
- vídeo de oferta direta
- vídeo educativo curto

Possíveis providers UGC futuros:

- Seedance 2.0 via BytePlus ModelArk
- MuAPI como camada opcional de teste
- outros providers compatíveis com geração de vídeo a partir de imagem, prompt e roteiro

A preferência técnica futura será usar APIs diretas dos fornecedores sempre que possível.

## Referência futura — Seedance 2.0 / BytePlus ModelArk

A futura funcionalidade de UGC deverá considerar a API oficial Seedance 2.0 via BytePlus ModelArk.

Documentação de referência:

https://docs.byteplus.com/en/docs/ModelArk/1520757

Uso previsto:

- geração de vídeos UGC
- vídeos estilo criador de conteúdo
- unboxing
- demonstração do produto
- review curto
- comparação
- apresentação persuasiva em formato natural

A integração Seedance 2.0 não faz parte do MVP inicial de vídeo com Veo + ElevenLabs, mas a arquitetura deve ser preparada para suportá-la como provider futuro.

## Regra principal

A implementação de vídeo deve ser incremental, modular e segura.

Não reescrever o plugin inteiro.
Não quebrar as etapas já existentes.
Não expor chaves de API no frontend.
Não implementar tudo de uma vez.
Não misturar lógica pesada de vídeo diretamente no app.js.
Não prender o sistema a uma única API de vídeo.

## Estratégia de desenvolvimento

Usar a pasta .bmad como memória permanente do projeto.

Antes de qualquer alteração no código, o Codex deve ler:

- .bmad/project-brief.md
- .bmad/architecture.md
- .bmad/active-context.md
- .bmad/video-pipeline.md
- .bmad/implementation-rules.md
- .bmad/api-contracts.md
- .bmad/decisions.md

O desenvolvimento será feito por etapas pequenas:

1. Documentar projeto.
2. Mapear arquitetura atual.
3. Adicionar configurações de vídeo no admin.
4. Adicionar configurações do ElevenLabs no admin.
5. Adicionar escolha de narração no passo 2.
6. Ajustar seleção de imagens para 4 a 8 no passo 4.
7. Criar módulo backend de vídeo.
8. Criar sistema de providers.
9. Criar endpoints AJAX.
10. Integrar Veo 3.1 Flash.
11. Integrar ElevenLabs.
12. Compor vídeo final.
13. Atualizar passo 5.
14. Atualizar resumo final.
15. Preparar arquitetura futura para UGC com Seedance 2.0 e MuAPI.

## Estrutura conceitual futura

O plugin deve evoluir para uma arquitetura baseada em providers:

Video Providers:

- VeoProvider
- SeedanceProvider
- MuApiProvider

Audio Providers:

- ElevenLabsProvider

Camada de orquestração:

- VideoService
- VideoJobService
- VideoComposer
- VideoStorage
- VideoAjaxController

O frontend deve apenas:

- coletar escolhas do usuário
- enviar dados para o backend
- exibir status
- exibir preview
- liberar download

O backend deve:

- proteger API keys
- criar jobs
- chamar providers externos
- salvar resultados
- registrar erros
- compor vídeo final
- devolver status para o frontend

## Critério de sucesso do MVP de vídeo

O MVP de vídeo será considerado concluído quando:

1. O usuário conseguir escolher o tipo de narração.
2. O usuário conseguir selecionar de 4 a 8 imagens.
3. O sistema conseguir gerar 4 clipes de 8 segundos.
4. O sistema conseguir gerar uma narração com ElevenLabs.
5. O sistema conseguir montar um vídeo final com áudio.
6. O vídeo final puder ser visualizado no passo 5.
7. O vídeo final puder ser baixado.
8. O vídeo final aparecer no resumo.
9. Nenhuma etapa anterior do plugin for quebrada.
10. As chaves de API permanecerem protegidas no backend/admin.

Depois de preencher o arquivo, confirme:
- que apenas .bmad/project-brief.md foi alterado;
- que nenhum arquivo PHP, JS ou CSS foi modificado.