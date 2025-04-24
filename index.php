<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Inicializar variáveis
$featured_products = [];

// Obter produtos em destaque se a função existir
if (function_exists('get_featured_products')) {
    $featured_products = get_featured_products();
}

// Obter depoimentos aprovados se a função existir
$testimonials = function_exists('get_approved_testimonials') ? get_approved_testimonials() : [];

// Obter jogos populares para a seção de jogos suportados
$popular_games = function_exists('get_popular_games') ? get_popular_games(6) : [];
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Premium</title>
    <meta name="description" content="Cheats premium para os melhores jogos. Suporte 24/7, atualizações constantes e segurança garantida.">
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="assets/images/favicon/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/images/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/images/favicon/favicon-16x16.png">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Toastify -->
    <link href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <!-- Fontes Gaming -->
    <link href="https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@400;500;600;700&family=Rajdhani:wght@400;500;600;700&family=Orbitron:wght@400;500;600;700;800&family=Audiowide&display=swap" rel="stylesheet">
    <!-- CSS Personalizado -->
    <link rel="stylesheet" href="assets/css/variables.css">
    <link rel="stylesheet" href="assets/css/landingpage.css">
</head>

<body>
    <!-- Loading Spinner -->
    <div class="loading">
        <div class="loading-spinner"></div>
    </div>

    <header class="header">
        <div class="container">
            <div class="logo">
                <a href="index.php"><?php echo SITE_NAME; ?></a>
            </div>

            <nav class="main-nav">
                <ul>
                    <li><a href="index.php" class="active">Home</a></li>
                    <li><a href="#features">Recursos</a></li>
                    <li><a href="#plans">Planos</a></li>
                    <li><a href="#testimonials">Depoimentos</a></li>
                    <li><a href="#faq">FAQ</a></li>
                </ul>
            </nav>

            <!-- Dentro do header e menu -->
            <div class="auth-buttons">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="dashboard/index.php" class="btn btn-primary">Minha Conta</a>
                <?php else: ?>
                    <a href="pages/login.php" class="btn btn-outline">Login</a>
                    <a href="pages/register.php" class="btn btn-primary">Registrar</a>
                <?php endif; ?>
            </div>

            <div class="mobile-menu-toggle">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </div>
    </header>

    <!-- Menu móvel (fora do header para evitar problemas de layout) -->
    <!-- No menu móvel -->
    <div class="mobile-menu">
        <ul>
            <li><a href="index.php" class="active">Home</a></li>
            <li><a href="#features">Recursos</a></li>
            <li><a href="#plans">Planos</a></li>
            <li><a href="#testimonials">Depoimentos</a></li>
            <li><a href="#faq">FAQ</a></li>
            <?php if (isset($_SESSION['user_id'])): ?>
                <li><a href="dashboard/index.php">Minha Conta</a></li>
            <?php else: ?>
                <li><a href="pages/login.php">Login</a></li>
                <li><a href="pages/register.php">Registrar</a></li>
            <?php endif; ?>
        </ul>
    </div>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="hero-content">
                <h1>Eleve seu jogo com os melhores cheats premium</h1>
                <p>Obtenha vantagem competitiva com nossos cheats de alta qualidade, atualizados constantemente e com suporte 24/7.</p>
                <!-- Nos botões de ação -->
                <div class="hero-buttons">
                    <a href="auth/register.php" class="btn btn-primary btn-large">Começar Agora</a>
                    <a href="#features" class="btn btn-outline btn-large">Saiba Mais</a>
                </div>
            </div>
            <div class="hero-image">
                <img src="assets/images/asd.png" alt="GestãoCheats Premium">
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="features fade-in">
        <div class="container">
            <div class="section-header">
                <h2>Por que escolher nossos cheats?</h2>
                <p>Oferecemos a melhor experiência para nossos clientes</p>
            </div>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3>Segurança Garantida</h3>
                    <p>Nossos cheats são indetectáveis e constantemente atualizados para evitar banimentos.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-sync-alt"></i>
                    </div>
                    <h3>Atualizações Constantes</h3>
                    <p>Mantemos nossos produtos sempre atualizados com as últimas versões dos jogos.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-headset"></i>
                    </div>
                    <h3>Suporte 24/7</h3>
                    <p>Nossa equipe está sempre disponível para ajudar com qualquer problema ou dúvida.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <h3>Desempenho Otimizado</h3>
                    <p>Cheats leves que não afetam o desempenho do seu jogo.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Plans Section -->
    <section id="plans" class="plans fade-in">
        <div class="container">
            <div class="section-header">
                <h2>Nossos Planos</h2>
                <p>Escolha o plano ideal para suas necessidades</p>
            </div>
            <div class="plans-overview">
                <div class="plan-card">
                    <div class="plan-name">Básico</div>
                    <div class="plan-price">
                        <span class="price">R$ 9,90</span>
                        <span class="period">/diário</span>
                    </div>
                    <div class="plan-features">
                        <ul>
                            <li><i class="fas fa-check"></i> Acesso a cheats básicos</li>
                            <li><i class="fas fa-check"></i> Suporte por email</li>
                            <li><i class="fas fa-check"></i> Atualizações mensais</li>
                            <li><i class="fas fa-times"></i> Recursos avançados</li>
                            <li><i class="fas fa-times"></i> Prioridade no suporte</li>
                        </ul>
                    </div>
                    <div class="plan-cta">
                        <a href="pages/register.php" class="btn btn-primary">Começar Agora</a>
                    </div>
                </div>
                <div class="plan-card featured">
                    <div class="plan-badge">Mais Popular</div>
                    <div class="plan-name">Premium</div>
                    <div class="plan-price">
                        <span class="price">R$ 49,90</span>
                        <span class="period">/semanal</span>
                    </div>
                    <div class="plan-features">
                        <ul>
                            <li><i class="fas fa-check"></i> Acesso a todos os cheats</li>
                            <li><i class="fas fa-check"></i> Suporte prioritário</li>
                            <li><i class="fas fa-check"></i> Atualizações semanais</li>
                            <li><i class="fas fa-check"></i> Acesso antecipado a novos cheats</li>
                            <li><i class="fas fa-times"></i> Recursos VIP exclusivos</li>
                        </ul>
                    </div>
                    <div class="plan-cta">
                        <a href="pages/register.php" class="btn btn-primary">Escolher Premium</a>
                    </div>
                </div>
                <div class="plan-card">
                    <div class="plan-name">Ultimate</div>
                    <div class="plan-price">
                        <span class="price">R$ 149,90</span>
                        <span class="period">/mensal</span>
                    </div>
                    <div class="plan-features">
                        <ul>
                            <li><i class="fas fa-check"></i> Acesso a todos os cheats</li>
                            <li><i class="fas fa-check"></i> Suporte VIP 24/7</li>
                            <li><i class="fas fa-check"></i> Atualizações diárias</li>
                            <li><i class="fas fa-check"></i> Acesso antecipado a novos cheats</li>
                            <li><i class="fas fa-check"></i> Recursos exclusivos</li>
                        </ul>
                    </div>
                    <div class="plan-cta">
                        <a href="pages/register.php" class="btn btn-primary">Escolher Ultimate</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Supported Games Section -->
    <section id="games" class="games fade-in">
        <div class="container">
            <div class="section-header">
                <h2>Jogos populares</h2>
                <p>Nossos cheats funcionam em diversos jogos populares</p>
            </div>
            <div class="games-grid">
                <!-- Free Fire -->
                <div class="game-card">
                    <div class="game-image">
                        <img src="assets/images/games/free-fire.jpg" alt="Free Fire">
                    </div>
                    <div class="game-overlay">
                        <h3>Free Fire</h3>
                        <a href="pages/register.php" class="btn btn-sm btn-primary">Ver Cheats</a>
                    </div>
                </div>
                <!-- FiveM -->
                <div class="game-card">
                    <div class="game-image">
                        <img src="assets/images/games/game_68074f36d3a5b.png" alt="FiveM">
                    </div>
                    <div class="game-overlay">
                        <h3>FiveM</h3>
                        <a href="pages/register.php" class="btn btn-sm btn-primary">Ver Cheats</a>
                    </div>
                </div>
                <!-- Warzone aim assist -->
                <div class="game-card">
                    <div class="game-image">
                        <img src="assets/images/games/warzone.jpg" alt="Warzone aim assist">
                    </div>
                    <div class="game-overlay">
                        <h3>Warzone aim assist</h3>
                        <a href="pages/register.php" class="btn btn-sm btn-primary">Ver Cheats</a>
                    </div>
                </div>
                <!-- Valorant -->
                <div class="game-card">
                    <div class="game-image">
                        <img src="assets/images/games/" alt="Valorant">
                    </div>
                    <div class="game-overlay">
                        <h3>Valorant</h3>
                        <a href="pages/register.php" class="btn btn-sm btn-primary">Ver Cheats</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section id="testimonials" class="testimonials fade-in">
        <div class="container">
            <div class="section-header">
                <h2>O que nossos clientes dizem</h2>
                <p>Depoimentos de usuários satisfeitos</p>
            </div>
            <div class="testimonials-slider">
                <?php if (!empty($testimonials)): ?>
                    <?php foreach ($testimonials as $testimonial): ?>
                        <div class="testimonial-card">
                            <div class="testimonial-rating">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star<?php echo ($i <= $testimonial['rating']) ? '' : '-o'; ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <div class="testimonial-content">
                                <p>"<?php echo $testimonial['content']; ?>"</p>
                            </div>
                            <div class="testimonial-author">
                                <div class="author-avatar">
                                    <?php if (!empty($testimonial['avatar'])): ?>
                                        <img src="<?php echo $testimonial['avatar']; ?>"
                                            alt="<?php echo $testimonial['name']; ?>">
                                    <?php else: ?>
                                        <img src="assets/images/avatar-default.png" alt="Avatar">
                                    <?php endif; ?>
                                </div>
                                <div class="author-name">
                                    <?php echo $testimonial['name']; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <!-- Depoimentos fictícios para demonstração -->
                    <div class="testimonial-card">
                        <div class="testimonial-rating">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                        <div class="testimonial-content">
                            <p>"Os cheats são simplesmente incríveis! Suporte super rápido e atualizações constantes. Estou
                                usando há 3 meses e nunca tive problemas."</p>
                        </div>
                        <div class="testimonial-author">
                            <div class="author-name">
                                João Silva
                            </div>
                        </div>
                    </div>
                    <div class="testimonial-card">
                        <div class="testimonial-rating">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star-half-alt"></i>
                        </div>
                        <div class="testimonial-content">
                            <p>"Melhor site de cheats que já usei. Atendimento muito bom e os cheats são atualizados
                                frequentemente. Recomendo!"</p>
                        </div>
                        <div class="testimonial-author">
                            <div class="author-name">
                                Maria Santos
                            </div>
                        </div>
                    </div>
                    <div class="testimonial-card">
                        <div class="testimonial-rating">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                        <div class="testimonial-content">
                            <p>"Assinei o plano Premium e estou extremamente satisfeito. Os cheats funcionam perfeitamente e
                                o suporte responde rapidamente quando preciso."</p>
                        </div>
                        <div class="testimonial-author">
                            <div class="author-name">
                                Carlos Oliveira
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section id="faq" class="faq fade-in">
        <div class="container">
            <div class="section-header">
                <h2>Perguntas Frequentes</h2>
                <p>Tudo o que você precisa saber sobre nossos cheats</p>
            </div>
            <div class="faq-list">
                <div class="faq-item">
                    <div class="faq-question">
                        <h3>Os cheats são seguros?</h3>
                        <div class="faq-toggle">
                            <i class="fas fa-chevron-down"></i>
                        </div>
                    </div>
                    <div class="faq-answer">
                        <p>Sim, nossos cheats são desenvolvidos com camadas avançadas de segurança para evitar detecção.
                            Atualizamos constantemente para garantir que permaneçam indetectáveis. No entanto, recomendamos
                            sempre usar de forma responsável e seguir nossas diretrizes de uso.</p>
                    </div>
                </div>
                <div class="faq-item">
                    <div class="faq-question">
                        <h3>Com que frequência os cheats são atualizados?</h3>
                        <div class="faq-toggle">
                            <i class="fas fa-chevron-down"></i>
                        </div>
                    </div>
                    <div class="faq-answer">
                        <p>Nossos cheats são atualizados regularmente para acompanhar as mudanças nos jogos. O plano Premium
                            recebe atualizações semanais, enquanto o plano Ultimate recebe atualizações diárias. O plano
                            Básico é atualizado mensalmente.</p>
                    </div>
                </div>
                <div class="faq-item">
                    <div class="faq-question">
                        <h3>Posso mudar de plano a qualquer momento?</h3>
                        <div class="faq-toggle">
                            <i class="fas fa-chevron-down"></i>
                        </div>
                    </div>
                    <div class="faq-answer">
                        <p>Sim, você pode fazer upgrade do seu plano a qualquer momento. O valor proporcional restante do
                            seu plano atual será automaticamente creditado no novo plano. Para fazer downgrade, você
                            precisará esperar até o fim do período atual.</p>
                    </div>
                </div>
                <div class="faq-item">
                    <div class="faq-question">
                        <h3>Como funciona o suporte técnico?</h3>
                        <div class="faq-toggle">
                            <i class="fas fa-chevron-down"></i>
                        </div>
                    </div>
                    <div class="faq-answer">
                        <p>Oferecemos suporte via chat e email. Clientes do plano Básico têm suporte por email com tempo de
                            resposta de até 24h. Clientes Premium recebem prioridade com tempo de resposta de até 6h.
                            Clientes Ultimate têm acesso ao suporte VIP 24/7 com tempo de resposta de até 1h.</p>
                    </div>
                </div>
                <div class="faq-item">
                    <div class="faq-question">
                        <h3>Quais métodos de pagamento são aceitos?</h3>
                        <div class="faq-toggle">
                            <i class="fas fa-chevron-down"></i>
                        </div>
                    </div>
                    <div class="faq-answer">
                        <p>Aceitamos cartões de crédito (Visa, Mastercard, American Express), PayPal, PagSeguro, PIX e
                            transferência bancária. Todas as transações são seguras e criptografadas para proteger seus
                            dados.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta fade-in">
        <div class="container">
            <div class="cta-content">
                <h2>Pronto para elevar seu jogo?</h2>
                <p>Junte-se a milhares de jogadores que já melhoraram sua experiência de jogo com nossos cheats premium.</p>
                <div class="cta-buttons">
                    <a href="pages/register.php" class="btn btn-primary btn-large">Começar Agora</a>
                    <a href="#plans" class="btn btn-outline btn-large">Ver Planos</a>
                </div>
            </div>
        </div>
    </section>
    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-column">
                    <div class="footer-logo">
                        <a href="index.php"><?php echo SITE_NAME; ?></a>
                    </div>
                    <p>Fornecendo cheats premium de alta qualidade com segurança garantida e suporte 24/7 para os melhores jogos do mercado.</p>
                    <div class="social-icons">
                        <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                        <a href="https://discord.gg/aH74U5rk7b" aria-label="Discord"><i class="fab fa-discord"></i></a>
                        <a href="#" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>
                <div class="footer-column">
                    <h3>Links Rápidos</h3>
                    <ul class="footer-links">
                        <li><a href="index.php">Home</a></li>
                        <li><a href="#features">Recursos</a></li>
                        <li><a href="#plans">Planos</a></li>
                        <li><a href="#games">Jogos</a></li>
                        <li><a href="#testimonials">Depoimentos</a></li>
                        <li><a href="#faq">FAQ</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>Suporte</h3>
                    <!-- No footer -->
                    <ul class="footer-links">
                        <li><a href="auth/login.php">Área do Cliente</a></li>
                        <li><a href="dashboard/support.php">Suporte Técnico</a></li>
                        <li><a href="pages/faq.php">Perguntas Frequentes</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>Contato</h3>
                    <div class="footer-contact">
                        <p><i class="fab fa-whatsapp"></i> (11) 99999-9999</p>
                        <p><i class="fab fa-discord"></i> https://discord.gg/aH74U5rk7b</p>
                        <p><i class="fas fa-clock"></i> Seg-Sex: 12:30h às 18h</p>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <div class="copyright">
                    &copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. Todos os direitos reservados.
                </div>
            </div>
        </div>
    </footer>

    <!-- Back to Top Button -->
    <div class="back-to-top">
        <i class="fas fa-arrow-up"></i>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script src="assets/js/main.js"></script>
    <script src="assets/js/loader.js"></script>
</body>

</html>