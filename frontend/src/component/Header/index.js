function Header() {
  return (
    <header className="site-header">
        <div className="header-inner">
        <a className="brand" href="#proximos" aria-label="Onde Passa o Jogo — início">
            <span className="brand-mark" aria-hidden="true">OP</span>
            <span>Onde Passa <strong>o Jogo</strong></span>
        </a>
        <nav className="main-nav" aria-label="Navegação principal">
            <a className="active" href="#proximos">Próximos jogos</a>
            <a href="#anteriores">Jogos anteriores</a>
        </nav>
        </div>
    </header>
  )
};

export default Header;
