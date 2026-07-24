import { useEffect, useMemo, useRef, useState } from 'react';
import { Alert, BackHandler, Image, KeyboardAvoidingView, Linking, Modal, Platform, Pressable, RefreshControl, ScrollView, StyleSheet, Switch, Text, View } from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import Constants from 'expo-constants';
import * as Device from 'expo-device';
import * as FileSystem from 'expo-file-system/legacy';
import * as AuthSession from 'expo-auth-session';
import * as Google from 'expo-auth-session/providers/google';
import * as ImageManipulator from 'expo-image-manipulator';
import * as ImagePicker from 'expo-image-picker';
import { LinearGradient } from 'expo-linear-gradient';
import * as Sharing from 'expo-sharing';
import * as WebBrowser from 'expo-web-browser';
import { StatusBar } from 'expo-status-bar';
import { SafeAreaProvider, useSafeAreaInsets } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { api, setApiBase, setToken } from './src/services/api';
import { colors, spacing } from './src/theme';
import FormField from './src/components/FormField';
import { PrimaryButton, SecondaryButton } from './src/components/Buttons';

const DEFAULT_API = Constants.expoConfig?.extra?.apiBase || 'https://balikos.balisantih.com/api/balikos';
const DEFAULT_PORTAL_ORIGIN = Constants.expoConfig?.extra?.portalOrigin || 'https://balikos.balisantih.com';
const APP_SCHEME = 'id.balisantih.balikos';
const GOOGLE_ANDROID_CLIENT_ID = '990876078905-agc2ej3m4uo4humpb07tuk0355uf54i7.apps.googleusercontent.com';
const MAX_ROOM_PHOTO_UPLOAD_BYTES = 1800 * 1024;
const balikosLogo = require('./assets/logo-balikos.png');
const googleLogo = require('./assets/google-icon.png');

WebBrowser.maybeCompleteAuthSession();

const today = () => new Date().toISOString().slice(0, 10);
const thisMonth = () => String(new Date().getMonth() + 1);
const thisYear = () => String(new Date().getFullYear());
const monthOptions = [
  { value: '1', label: 'Jan' },
  { value: '2', label: 'Feb' },
  { value: '3', label: 'Mar' },
  { value: '4', label: 'Apr' },
  { value: '5', label: 'Mei' },
  { value: '6', label: 'Jun' },
  { value: '7', label: 'Jul' },
  { value: '8', label: 'Agu' },
  { value: '9', label: 'Sep' },
  { value: '10', label: 'Okt' },
  { value: '11', label: 'Nov' },
  { value: '12', label: 'Des' },
];

const emptyKosForm = { id: null, nama_kos: '', alamat: '', kecamatan: '', desa: '', banjar: '', no_wa: '', status: 'aktif' };
const emptyRoomForm = {
  id: null,
  has_active_occupant: false,
  nomor_kamar: '',
  tipe_kamar: 'Standard',
  harga_bulanan: '',
  status: 'kosong',
  fasilitas_ac: false,
  fasilitas_km_dalam: true,
  fasilitas_dapur_dalam: false,
  fasilitas_wifi: true,
  fasilitas_kasur: true,
  fasilitas_lemari: false,
  fasilitas_meja: false,
  fasilitas_parkir: true,
  catatan: '',
  fotos: [],
  existing_fotos: [],
  hapus_foto_ids: [],
};
const emptyOccupantForm = {
  id: null,
  kamar_id: '',
  nama_lengkap: '',
  no_ktp: '',
  foto_ktp: null,
  existing_foto_ktp: null,
  no_wa: '',
  alamat_asal: '',
  pekerjaan: '',
  no_kendaraan: '',
  kontak_darurat: '',
  catatan_pemilik: '',
  tanggal_masuk: today(),
  status: 'aktif',
  pembayaran_awal: 'belum_bayar',
  nominal_pembayaran_awal: '',
};
const emptyBillForm = { kamar_id: '', bulan: thisMonth(), tahun: thisYear(), jumlah_bulan: '1' };
const emptyMultiPayForm = { kamar_id: '', penghuni_id: '', bulan: thisMonth(), tahun: thisYear(), jumlah_bulan: '2', tanggal_bayar: today(), metode_pembayaran: 'tunai' };
const emptyRoomStatusForm = { id: '', nomor_kamar: '', status: 'kosong' };
const emptyPaymentForm = { jenis: 'qris', nama_bank: '', nomor_rekening: '', atas_nama: '', instruksi_pembayaran: '', is_active: true };
const emptyFinanceForm = { id: null, jenis: 'pemasukan', tanggal: today(), nominal: '', keterangan: '' };
const emptyAnnouncementForm = { id: null, judul: '', isi: '', status: 'aktif' };
const emptyWithdrawForm = { nominal: '', nama_bank: '', nomor_rekening: '', atas_nama: '' };
const emptyInitialPaymentForm = { id: '', nominal: '', tanggal_bayar: today(), harga_kamar: 0 };

export default function App() {
  return (
    <SafeAreaProvider>
      <BalikosApp />
    </SafeAreaProvider>
  );
}

function BalikosApp() {
  const safeInsets = useSafeAreaInsets();
  const [booting, setBooting] = useState(true);
  const [tokenValue, setTokenValue] = useState(null);
  const [apiBase, setApiBaseValue] = useState(DEFAULT_API);
  const [authMode, setAuthMode] = useState('login');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [register, setRegister] = useState({ name: '', email: '', phone: '', password: '', password_confirmation: '' });
  const [loading, setLoading] = useState(false);
  const [refreshing, setRefreshing] = useState(false);
  const [tab, setTab] = useState('dashboard');
  const [moreScreen, setMoreScreen] = useState('payment');
  const [occupantFilter, setOccupantFilter] = useState('semua');
  const [kosList, setKosList] = useState([]);
  const [activeKosId, setActiveKosId] = useState(null);
  const [dashboard, setDashboard] = useState(null);
  const [rooms, setRooms] = useState([]);
  const [occupants, setOccupants] = useState([]);
  const [bills, setBills] = useState([]);
  const [paymentMethods, setPaymentMethods] = useState([]);
  const [paymentWallet, setPaymentWallet] = useState(null);
  const [finances, setFinances] = useState([]);
  const [financeSummary, setFinanceSummary] = useState(null);
  const [financeFilter, setFinanceFilter] = useState({ bulan: thisMonth(), tahun: thisYear() });
  const [announcements, setAnnouncements] = useState([]);
  const [roomDetail, setRoomDetail] = useState(null);
  const [occupantDetail, setOccupantDetail] = useState(null);
  const [imagePreview, setImagePreview] = useState(null);
  const [kosForm, setKosForm] = useState(emptyKosForm);
  const [roomForm, setRoomForm] = useState(emptyRoomForm);
  const [occupantForm, setOccupantForm] = useState(emptyOccupantForm);
  const [billForm, setBillForm] = useState(emptyBillForm);
  const [multiPayForm, setMultiPayForm] = useState(emptyMultiPayForm);
  const [multiPayContext, setMultiPayContext] = useState(null);
  const [roomStatusForm, setRoomStatusForm] = useState(emptyRoomStatusForm);
  const [paymentForm, setPaymentForm] = useState(emptyPaymentForm);
  const [financeForm, setFinanceForm] = useState(emptyFinanceForm);
  const [announcementForm, setAnnouncementForm] = useState(emptyAnnouncementForm);
  const [withdrawForm, setWithdrawForm] = useState(emptyWithdrawForm);
  const [initialPaymentForm, setInitialPaymentForm] = useState(emptyInitialPaymentForm);
  const [modal, setModal] = useState(null);
  const [periodDraft, setPeriodDraft] = useState({ bulan: thisMonth(), tahun: thisYear() });
  const waitingVerificationRef = useRef(null);
  const sessionVersionRef = useRef(0);
  const activeKosIdRef = useRef(null);
  const googleWebClientId = Constants.expoConfig?.extra?.googleWebClientId || Constants.manifest?.extra?.googleWebClientId || '';
  const googleAndroidClientId = Constants.expoConfig?.extra?.googleAndroidClientId || Constants.manifest?.extra?.googleAndroidClientId || GOOGLE_ANDROID_CLIENT_ID;

  const activeKos = useMemo(() => kosList.find((item) => item.id === activeKosId), [kosList, activeKosId]);
  const emptyRooms = useMemo(() => rooms.filter((room) => room.status === 'kosong'), [rooms]);

  useEffect(() => {
    (async () => {
      const storedApi = await AsyncStorage.getItem('api_base');
      const oldApiHosts = ['http://10.0.2.2/api/balikos', 'https://api.balikos.id/api/balikos'];
      const savedApi = !storedApi || oldApiHosts.includes(storedApi) ? DEFAULT_API : storedApi;
      const savedToken = await AsyncStorage.getItem('token');
      setApiBaseValue(savedApi);
      setApiBase(savedApi);
      if (savedToken) {
        setTokenValue(savedToken);
        setToken(savedToken);
      }
      setBooting(false);
    })();
  }, []);

  useEffect(() => {
    if (tokenValue) loadKos();
  }, [tokenValue]);

  useEffect(() => {
    if (tokenValue) registerPushToken();
  }, [tokenValue]);

  useEffect(() => {
    if (!tokenValue || Constants.appOwnership === 'expo' || Constants.executionEnvironment === 'storeClient') return undefined;

    let responseSubscription;
    let cancelled = false;
    (async () => {
      const Notifications = await import('expo-notifications');
      responseSubscription = Notifications.addNotificationResponseReceivedListener((response) => {
        openNotificationTarget(response.notification.request.content.data);
      });
      const lastResponse = await Notifications.getLastNotificationResponseAsync();
      if (!cancelled && lastResponse) {
        openNotificationTarget(lastResponse.notification.request.content.data);
        await Notifications.clearLastNotificationResponseAsync();
      }
    })().catch((error) => console.warn('Notification listener setup failed', error?.message || error));

    return () => {
      cancelled = true;
      responseSubscription?.remove();
    };
  }, [tokenValue]);

  useEffect(() => {
    activeKosIdRef.current = activeKosId;
  }, [activeKosId]);

  useEffect(() => {
    if (!tokenValue || !activeKosId) return;
    if (tab === 'dashboard') loadDashboard();
    if (tab === 'kamar') loadRooms();
    if (tab === 'penghuni') loadOccupantWorkspace();
    if (tab === 'lainnya') loadMore();
  }, [tokenValue, activeKosId, tab, moreScreen, financeFilter.bulan, financeFilter.tahun]);

  useEffect(() => {
    if (!tokenValue || !activeKosId || tab !== 'penghuni') return;
    const timer = setInterval(checkBillUpdates, 15000);
    return () => clearInterval(timer);
  }, [tokenValue, activeKosId, tab]);

  useEffect(() => {
    if (Platform.OS !== 'android') return undefined;
    const subscription = BackHandler.addEventListener('hardwareBackPress', () => {
      if (imagePreview) {
        setImagePreview(null);
        return true;
      }
      if (modal) {
        setModal(null);
        setMultiPayContext(null);
        setRoomForm(emptyRoomForm);
        return true;
      }
      if (roomDetail) {
        setRoomDetail(null);
        return true;
      }
      if (occupantDetail) {
        setOccupantDetail(null);
        return true;
      }
      if (tab === 'lainnya' && moreScreen !== 'payment') {
        setMoreScreen('payment');
        return true;
      }
      if (tokenValue && tab !== 'dashboard') {
        setTab('dashboard');
        return true;
      }
      return false;
    });
    return () => subscription.remove();
  }, [imagePreview, modal, roomDetail, occupantDetail, tab, moreScreen, tokenValue]);

  function clearKosScopedState() {
    setDashboard(null);
    setRooms([]);
    setOccupants([]);
    setBills([]);
    setPaymentMethods([]);
    setPaymentWallet(null);
    setFinances([]);
    setFinanceSummary(null);
    setAnnouncements([]);
    setRoomDetail(null);
    setOccupantDetail(null);
    setImagePreview(null);
    setModal(null);
    setRoomForm(emptyRoomForm);
    setOccupantForm(emptyOccupantForm);
    setBillForm(emptyBillForm);
    setMultiPayForm(emptyMultiPayForm);
    setMultiPayContext(null);
    setRoomStatusForm(emptyRoomStatusForm);
    setPaymentForm(emptyPaymentForm);
    setFinanceForm(emptyFinanceForm);
    setAnnouncementForm(emptyAnnouncementForm);
    setWithdrawForm(emptyWithdrawForm);
    waitingVerificationRef.current = null;
  }

  function resetAccountState() {
    sessionVersionRef.current += 1;
    activeKosIdRef.current = null;
    clearKosScopedState();
    setKosList([]);
    setActiveKosId(null);
    setKosForm(emptyKosForm);
    setTab('dashboard');
    setMoreScreen('payment');
    setOccupantFilter('semua');
    setFinanceFilter({ bulan: thisMonth(), tahun: thisYear() });
    setPeriodDraft({ bulan: thisMonth(), tahun: thisYear() });
  }

  function changeActiveKos(nextKosId) {
    const normalizedId = nextKosId || null;
    if (String(normalizedId || '') === String(activeKosIdRef.current || '')) return;
    activeKosIdRef.current = normalizedId;
    clearKosScopedState();
    setActiveKosId(normalizedId);
  }

  function currentKosRequest() {
    return {
      session: sessionVersionRef.current,
      kosId: activeKosIdRef.current || activeKosId,
    };
  }

  function isCurrentKosRequest(context) {
    return context.session === sessionVersionRef.current
      && String(context.kosId || '') === String(activeKosIdRef.current || '');
  }

  async function saveAuth(nextToken) {
    resetAccountState();
    setPassword('');
    setTokenValue(nextToken);
    setToken(nextToken);
    setApiBase(apiBase);
    await AsyncStorage.setItem('token', nextToken);
    await AsyncStorage.setItem('api_base', apiBase);
  }

  async function registerPushToken() {
    try {
      if (Constants.appOwnership === 'expo' || Constants.executionEnvironment === 'storeClient') return;
      if (!Device.isDevice) return;
      const Notifications = await import('expo-notifications');
      Notifications.setNotificationHandler({
        handleNotification: async () => ({
          shouldShowBanner: true,
          shouldShowList: true,
          shouldPlaySound: true,
          shouldSetBadge: true,
        }),
      });
      await Notifications.setNotificationChannelAsync('payments', {
        name: 'Pembayaran dan Tagihan',
        importance: Notifications.AndroidImportance.MAX,
        vibrationPattern: [0, 250, 250, 250],
        lightColor: '#0a63c7',
      });
      const current = await Notifications.getPermissionsAsync();
      const finalStatus = current.status === 'granted'
        ? current.status
        : (await Notifications.requestPermissionsAsync()).status;
      if (finalStatus !== 'granted') {
        console.warn('Izin notifikasi belum diberikan.');
        return;
      }
      const deviceToken = await Notifications.getDevicePushTokenAsync();
      if (deviceToken.type !== 'fcm' || !deviceToken.data) {
        console.warn('Token FCM tidak tersedia pada perangkat ini.');
        return;
      }
      const token = deviceToken.data;
      await api('/push-token', { method: 'POST', body: { token, provider: 'fcm', device_name: Device.modelName || 'android' } });
      await AsyncStorage.setItem('push_notification_token', token);
    } catch (error) {
      console.warn('Push token registration failed', error?.message || error);
    }
  }

  function openNotificationTarget(data = {}) {
    if (data.kos_id) {
      const kosId = Number(data.kos_id);
      if (kosList.some((item) => Number(item.id) === kosId)) setActiveKosId(kosId);
    }
    if (data.type === 'payment_proof') {
      setOccupantFilter('verifikasi');
      setTab('penghuni');
    } else if (data.type === 'due_bills') {
      setOccupantFilter('jatuh_tempo');
      setTab('penghuni');
    } else if (data.type === 'qris_paid') {
      setOccupantFilter('semua');
      setTab('penghuni');
    }
  }

  async function doLogin() {
    await submit(async () => {
      const response = await api('/login', { method: 'POST', body: { email, password, device_name: 'expo-mobile' }, auth: false });
      await saveAuth(response.token);
    }, 'Login gagal');
  }

  async function doRegister() {
    await submit(async () => {
      const response = await api('/register', { method: 'POST', body: { ...register, device_name: 'expo-mobile' }, auth: false });
      await saveAuth(response.token);
    }, 'Pendaftaran gagal');
  }

  async function doGoogleLogin(idToken) {
    await submit(async () => {
      const response = await api('/google-login', { method: 'POST', body: { id_token: idToken, device_name: 'google-mobile' }, auth: false });
      await saveAuth(response.token);
    }, 'Login Google gagal');
  }

  async function submit(action, title = 'Gagal') {
    try {
      setLoading(true);
      await action();
    } catch (error) {
      Alert.alert(title, error.message);
    } finally {
      setLoading(false);
    }
  }

  async function loadKos() {
    const requestSession = sessionVersionRef.current;
    const response = await api('/kos');
    if (requestSession !== sessionVersionRef.current) return;

    const nextKos = response.data || [];
    setKosList(nextKos);
    const currentKosStillAvailable = nextKos.some((item) => String(item.id) === String(activeKosIdRef.current));
    const nextKosId = currentKosStillAvailable ? activeKosIdRef.current : (nextKos[0]?.id || null);
    if (String(nextKosId || '') !== String(activeKosIdRef.current || '')) changeActiveKos(nextKosId);
  }

  async function loadDashboard() {
    const context = currentKosRequest();
    if (!context.kosId) return;
    const [dashboardResponse, occupantResponse] = await Promise.all([
      api(`/dashboard?kos_id=${context.kosId}`),
      api(`/penghuni?kos_id=${context.kosId}`),
    ]);
    if (!isCurrentKosRequest(context)) return;
    setDashboard(dashboardResponse.data);
    setOccupants(occupantResponse.data || []);
  }

  async function loadRooms() {
    const context = currentKosRequest();
    if (!context.kosId) return;
    const response = await api(`/kamar?kos_id=${context.kosId}`);
    if (!isCurrentKosRequest(context)) return;
    const nextRooms = response.data || [];
    setRooms(nextRooms);
    prefetchRoomPhotos(nextRooms, apiBase);
  }

  async function loadOccupants() {
    const context = currentKosRequest();
    if (!context.kosId) return;
    const response = await api(`/penghuni?kos_id=${context.kosId}`);
    if (!isCurrentKosRequest(context)) return;
    setOccupants(response.data || []);
    if (rooms.length === 0) loadRooms();
  }

  async function loadOccupantWorkspace() {
    await Promise.all([loadRooms(), loadOccupants(), loadBills()]);
  }

  async function loadBills() {
    const context = currentKosRequest();
    if (!context.kosId) return;
    const response = await api(`/tagihan?kos_id=${context.kosId}`);
    if (!isCurrentKosRequest(context)) return;
    setBills(response.data || []);
    waitingVerificationRef.current = (response.data || []).filter((bill) => bill.status === 'menunggu_verifikasi').length;
    if (rooms.length === 0) loadRooms();
  }

  async function checkBillUpdates() {
    try {
      const context = currentKosRequest();
      if (!context.kosId) return;
      const response = await api(`/tagihan?kos_id=${context.kosId}`);
      if (!isCurrentKosRequest(context)) return;
      const nextBills = response.data || [];
      const waitingCount = nextBills.filter((bill) => bill.status === 'menunggu_verifikasi').length;
      if (waitingVerificationRef.current !== null && waitingCount > waitingVerificationRef.current) {
        Alert.alert('Pembayaran baru', 'Ada bukti pembayaran penghuni yang perlu diverifikasi.');
      }
      waitingVerificationRef.current = waitingCount;
      setBills(nextBills);
      await loadOccupants();
    } catch {}
  }

  async function loadMore() {
    const context = currentKosRequest();
    if (!context.kosId) return;
    if (moreScreen === 'payment') {
      const response = await api(`/payment-methods?kos_id=${context.kosId}`);
      if (!isCurrentKosRequest(context)) return;
      setPaymentMethods(response.data || []);
      setPaymentWallet(response.wallet || null);
    }
    if (moreScreen === 'finance') {
      const response = await api(`/keuangan?kos_id=${context.kosId}&bulan=${financeFilter.bulan}&tahun=${financeFilter.tahun}`);
      if (!isCurrentKosRequest(context)) return;
      setFinances(response.data || []);
      setFinanceSummary(response.summary || null);
    }
    if (moreScreen === 'announcement') {
      const response = await api(`/pengumuman?kos_id=${context.kosId}`);
      if (!isCurrentKosRequest(context)) return;
      setAnnouncements(response.data || []);
    }
  }

  async function refreshActiveScreen() {
    if (!tokenValue) return;
    setRefreshing(true);
    try {
      await loadKos();
      if (!activeKosId) return;
      if (tab === 'dashboard') await loadDashboard();
      if (tab === 'kamar') await loadRooms();
      if (tab === 'penghuni') await loadOccupantWorkspace();
      if (tab === 'lainnya') await loadMore();
    } catch (error) {
      Alert.alert('Gagal refresh', error.message || 'Data belum bisa dimuat ulang.');
    } finally {
      setRefreshing(false);
    }
  }

  async function openRoomDetail(id) {
    await submit(async () => {
      const response = await api(`/kamar/${id}`);
      setRoomDetail(response.data);
    }, 'Gagal memuat detail kamar');
  }

  function openRoomCreateModal() {
    setRoomForm(emptyRoomForm);
    setModal('room');
  }

  function openRoomEditModal(room) {
    setRoomDetail(null);
    setRoomForm(roomToForm(room));
    setModal('room');
  }

  async function pickRoomImage() {
    const used = (roomForm.existing_fotos?.length || 0) + (roomForm.fotos?.length || 0);
    const remaining = Math.max(0, 5 - used);
    if (remaining <= 0) return Alert.alert('Foto sudah maksimal', 'Maksimal 5 foto untuk setiap kamar.');
    const permission = await ImagePicker.requestMediaLibraryPermissionsAsync();
    if (!permission.granted) return Alert.alert('Izin foto diperlukan', 'Izinkan akses foto agar bisa upload foto kamar.');
    const result = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ImagePicker.MediaTypeOptions.Images,
      allowsMultipleSelection: true,
      selectionLimit: remaining,
      quality: 0.45,
      exif: false,
    });
    if (!result.canceled) {
      setLoading(true);
      try {
        const selected = [];
        for (const [index, asset] of (result.assets || []).slice(0, remaining).entries()) {
          selected.push(await prepareRoomPhotoForUpload(asset, index));
        }
        setRoomForm((current) => ({
          ...current,
          fotos: [...(current.fotos || []), ...selected].slice(0, 5),
        }));
      } catch (error) {
        Alert.alert('Foto belum bisa dipakai', error.message || 'Pilih foto lain dengan ukuran lebih kecil.');
      } finally {
        setLoading(false);
      }
    }
  }

  async function pickKtpImage() {
    const result = await ImagePicker.launchImageLibraryAsync({ mediaTypes: ImagePicker.MediaTypeOptions.Images, quality: 0.8 });
    if (!result.canceled) setOccupantForm((current) => ({ ...current, foto_ktp: result.assets[0] }));
  }

  async function saveKos() {
    if (!kosForm.nama_kos.trim()) return Alert.alert('Nama kos wajib diisi', 'Contoh: Kos Melati Denpasar.');
    if (!kosForm.alamat.trim()) return Alert.alert('Alamat wajib diisi', 'Isi alamat lengkap kos.');
    if (!kosForm.kecamatan.trim()) return Alert.alert('Kecamatan wajib diisi', 'Contoh: Denpasar Selatan.');
    await submit(async () => {
      const payload = { ...kosForm };
      delete payload.id;
      const response = await api(kosForm.id ? `/kos/${kosForm.id}` : '/kos', { method: kosForm.id ? 'PUT' : 'POST', body: payload });
      const createdId = response.data?.id;
      setKosForm(emptyKosForm);
      await loadKos();
      if (createdId) changeActiveKos(createdId);
      setModal(null);
    }, 'Gagal menyimpan kos');
  }

  function openKosCreateModal() {
    setKosForm(emptyKosForm);
    setModal('kos');
  }

  function openKosEditModal() {
    if (!activeKos) return Alert.alert('Pilih kos', 'Pilih kos yang ingin diedit.');
    setKosForm({
      id: activeKos.id,
      nama_kos: activeKos.nama_kos || '',
      alamat: activeKos.alamat || '',
      kecamatan: activeKos.kecamatan || '',
      desa: activeKos.desa || '',
      banjar: activeKos.banjar || '',
      no_wa: activeKos.no_wa || '',
      status: activeKos.status || 'aktif',
    });
    setModal('kos');
  }

  async function deleteKos() {
    if (!kosForm.id) return;
    Alert.alert(
      'Hapus kos?',
      `Data ${kosForm.nama_kos} akan dihapus beserta kamar, penghuni, tagihan, dan data lain di dalamnya. Lanjutkan hanya jika benar-benar yakin.`,
      [
        { text: 'Batal', style: 'cancel' },
        {
          text: 'Hapus',
          style: 'destructive',
          onPress: async () => {
            await submit(async () => {
              await api(`/kos/${kosForm.id}`, { method: 'DELETE' });
              setModal(null);
              setKosForm(emptyKosForm);
              const response = await api('/kos');
              const nextKos = response.data || [];
              setKosList(nextKos);
              changeActiveKos(nextKos[0]?.id || null);
            }, 'Gagal menghapus kos');
          },
        },
      ],
    );
  }

  async function saveRoom() {
    if (!activeKosId) return Alert.alert('Pilih kos', 'Pilih kos aktif terlebih dahulu.');
    if (!roomForm.nomor_kamar.trim()) return Alert.alert('Nomor kamar wajib diisi', 'Contoh: A1, B-02, atau 101.');
    if (!cleanNumber(roomForm.harga_bulanan)) return Alert.alert('Harga wajib diisi', 'Isi angka saja, contoh 1200000.');
    await submit(async () => {
      const form = new FormData();
      form.append('kos_id', String(activeKosId));
      if (roomForm.id) form.append('_method', 'PUT');
      Object.entries(roomForm).forEach(([key, value]) => {
        if (['id', 'has_active_occupant', 'fotos', 'existing_fotos', 'hapus_foto_ids'].includes(key) || value === null || value === '') return;
        const payloadValue = key === 'harga_bulanan' ? cleanNumber(value) : value;
        form.append(key, typeof payloadValue === 'boolean' ? (payloadValue ? '1' : '0') : String(payloadValue));
      });
      (roomForm.fotos || []).forEach((photo, index) => {
        if (photo.fileSize && photo.fileSize > MAX_ROOM_PHOTO_UPLOAD_BYTES) {
          throw new Error(`Foto ${index + 1} terlalu besar. Hapus foto itu lalu pilih ulang agar aplikasi mengompresi otomatis.`);
        }
        const payload = { uri: photo.uri, name: photo.fileName || `kamar-${index + 1}.jpg`, type: photo.mimeType || 'image/jpeg' };
        if (index === 0) form.append('foto', payload);
        else form.append('fotos[]', payload);
      });
      (roomForm.hapus_foto_ids || []).forEach((id) => form.append('hapus_foto_ids[]', String(id)));
      await api(roomForm.id ? `/kamar/${roomForm.id}` : '/kamar', { method: 'POST', body: form, isMultipart: true });
      setModal(null);
      setRoomForm(emptyRoomForm);
      await loadRooms();
      await loadDashboard();
    }, 'Gagal menyimpan kamar');
  }

  function openOccupantModal(room = null) {
    setRoomDetail(null);
    setOccupantForm({ ...emptyOccupantForm, kamar_id: room ? String(room.id) : '' });
    setModal('occupant');
    if (rooms.length === 0) loadRooms();
  }

  function openOccupantEditModal(occupant) {
    setOccupantDetail(null);
    setOccupantForm({
      id: occupant.id,
      kamar_id: String(occupant.kamar_id || ''),
      nama_lengkap: occupant.nama_lengkap || '',
      no_ktp: occupant.no_ktp || '',
      foto_ktp: null,
      existing_foto_ktp: occupant.foto_ktp || null,
      no_wa: occupant.no_wa || '',
      alamat_asal: occupant.alamat_asal || '',
      pekerjaan: occupant.pekerjaan || '',
      no_kendaraan: occupant.no_kendaraan || '',
      kontak_darurat: occupant.kontak_darurat || '',
      catatan_pemilik: occupant.catatan_pemilik || '',
      tanggal_masuk: occupant.tanggal_masuk || today(),
      status: occupant.status || 'aktif',
      pembayaran_awal: 'belum_bayar',
      nominal_pembayaran_awal: '',
    });
    setModal('occupant');
    if (rooms.length === 0) loadRooms();
  }

  async function saveOccupant() {
    if (!activeKosId) return Alert.alert('Pilih kos', 'Pilih kos aktif terlebih dahulu.');
    if (!occupantForm.kamar_id) return Alert.alert('Pilih kamar', occupantForm.id ? 'Data kamar penghuni tidak ditemukan.' : 'Pilih kamar kosong untuk penghuni ini.');
    if (!occupantForm.nama_lengkap.trim()) return Alert.alert('Nama penghuni wajib diisi', 'Isi nama lengkap penghuni.');
    if (!occupantForm.tanggal_masuk.trim()) return Alert.alert('Tanggal masuk wajib diisi', 'Pilih tanggal masuk penghuni.');
    if (!occupantForm.id && occupantForm.pembayaran_awal === 'dp' && !cleanNumber(occupantForm.nominal_pembayaran_awal)) {
      return Alert.alert('Nominal DP wajib diisi', 'Isi nominal DP yang sudah dibayar penghuni sebelum masuk kamar.');
    }
    await submit(async () => {
      const masuk = dateParts(occupantForm.tanggal_masuk);
      const jatuhTempoHari = Math.min(28, Math.max(1, masuk.day));
      const form = new FormData();
      form.append('kos_id', String(activeKosId));
      form.append('kamar_id', String(Number(occupantForm.kamar_id)));
      form.append('jatuh_tempo_hari', String(jatuhTempoHari));
      if (occupantForm.id) form.append('_method', 'PUT');
      Object.entries(occupantForm).forEach(([key, value]) => {
        if (['id', 'foto_ktp', 'existing_foto_ktp'].includes(key) || value === null || value === '') return;
        if (key === 'nominal_pembayaran_awal') form.append(key, cleanNumber(value));
        else form.append(key, String(value));
      });
      if (occupantForm.foto_ktp) {
        form.append('foto_ktp', {
          uri: occupantForm.foto_ktp.uri,
          name: occupantForm.foto_ktp.fileName || 'ktp.jpg',
          type: occupantForm.foto_ktp.mimeType || 'image/jpeg',
        });
      }
      await api(occupantForm.id ? `/penghuni/${occupantForm.id}` : '/penghuni', { method: 'POST', body: form, isMultipart: true });
      setModal(null);
      setOccupantForm(emptyOccupantForm);
      await loadOccupants();
      await loadRooms();
      await loadDashboard();
    }, 'Gagal menyimpan penghuni');
  }

  function openRoomStatusModal(room) {
    setRoomDetail(null);
    setRoomStatusForm({ id: String(room.id), nomor_kamar: room.nomor_kamar, status: room.status });
    setModal('roomStatus');
  }

  function openPeriodModal() {
    setPeriodDraft({ ...financeFilter });
    setModal('period');
  }

  function applyPeriod() {
    setFinanceFilter({ ...periodDraft });
    setModal(null);
  }

  async function saveRoomStatus() {
    await submit(async () => {
      await api(`/kamar/${roomStatusForm.id}`, { method: 'PUT', body: { status: roomStatusForm.status } });
      setModal(null);
      setRoomStatusForm(emptyRoomStatusForm);
      await loadRooms();
      await loadDashboard();
    }, 'Gagal mengubah status kamar');
  }

  async function checkoutOccupant(occupant) {
    const outstanding = Number(occupant.tagihan_aktif_nominal || 0);
    const lossNotice = outstanding > 0
      ? `\n\nSisa tunggakan ${money(outstanding)} akan otomatis tercatat sebagai kerugian pada laporan bulan ini.`
      : '';
    Alert.alert(
      'Keluarkan penghuni?',
      `Penghuni ${occupant.nama_lengkap} akan ditandai keluar hari ini. Jika salah, data penghuni harus diaktifkan/diinput ulang.${lossNotice}`,
      [
        { text: 'Batal', style: 'cancel' },
        {
          text: 'Ya, Keluarkan',
          style: 'destructive',
          onPress: async () => {
            await submit(async () => {
              await api(`/penghuni/${occupant.id}`, { method: 'PUT', body: { status: 'keluar', tanggal_keluar: today() } });
              setRoomDetail(null);
              setOccupantDetail(null);
              await loadOccupants();
              await loadRooms();
              await loadDashboard();
              Alert.alert('Penghuni dikeluarkan', outstanding > 0 ? 'Kamar kembali kosong dan tunggakan sudah masuk ke kerugian laporan.' : 'Status penghuni menjadi keluar dan kamar kembali kosong.');
            }, 'Gagal mengeluarkan penghuni');
          },
        },
      ],
    );
  }

  async function generateBills() {
    if (!billForm.bulan || !billForm.tahun) return Alert.alert('Data tagihan belum lengkap', 'Isi bulan dan tahun tagihan.');
    await submit(async () => {
      const body = { kos_id: activeKosId, bulan: Number(billForm.bulan), tahun: Number(billForm.tahun), jumlah_bulan: Number(billForm.jumlah_bulan || 1) };
      if (billForm.kamar_id) body.kamar_id = Number(billForm.kamar_id);
      const response = await api('/tagihan/generate', { method: 'POST', body });
      setModal(null);
      await loadBills();
      await loadOccupants();
      Alert.alert('Tagihan dibuat', `${response.total || 0} tagihan diproses.`);
    }, 'Gagal membuat tagihan');
  }

  async function payMultiMonth() {
    if (!multiPayForm.kamar_id) return Alert.alert('Pilih kamar', 'Pilih kamar penghuni yang membayar.');
    if (!multiPayForm.jumlah_bulan || Number(multiPayForm.jumlah_bulan) < 1) return Alert.alert('Jumlah bulan belum valid', 'Isi 2, 3, atau jumlah bulan lain.');
    const jumlahBulan = Number(multiPayForm.jumlah_bulan);
    const mulaiBulan = monthOptions.find((item) => item.value === String(multiPayForm.bulan))?.label || `Bulan ${multiPayForm.bulan}`;
    const targetName = multiPayContext?.nama_lengkap || `Kamar ${roomName(rooms, multiPayForm.kamar_id)}`;
    const metodeBayar = multiPayContext ? 'tunai' : multiPayForm.metode_pembayaran;
    Alert.alert(
      metodeBayar === 'tunai' ? 'Catat bayar tunai?' : 'Catat pembayaran?',
      `${targetName}\nMulai ${mulaiBulan} ${multiPayForm.tahun}\nJumlah ${jumlahBulan} bulan\nTanggal bayar ${formatDate(multiPayForm.tanggal_bayar)}\nMetode ${paymentMethodLabel(metodeBayar)}\n\nPastikan pembayaran sudah diterima sebelum melanjutkan.`,
      [
        { text: 'Cek lagi', style: 'cancel' },
        { text: 'Ya, Catat Bayar', onPress: () => submitMultiMonthPayment(jumlahBulan, metodeBayar) },
      ],
    );
  }

  async function submitMultiMonthPayment(jumlahBulan, metodeBayar) {
    await submit(async () => {
      const response = await api('/tagihan/bayar-multi', {
        method: 'POST',
        body: {
          kos_id: activeKosId,
          kamar_id: Number(multiPayForm.kamar_id),
          bulan: Number(multiPayForm.bulan),
          tahun: Number(multiPayForm.tahun),
          jumlah_bulan: jumlahBulan,
          tanggal_bayar: multiPayForm.tanggal_bayar,
          metode_pembayaran: metodeBayar,
          penghuni_id: multiPayForm.penghuni_id ? Number(multiPayForm.penghuni_id) : undefined,
        },
      });
      setModal(null);
      setMultiPayContext(null);
      await loadBills();
      await loadOccupants();
      Alert.alert('Pembayaran dicatat', `${response.total || 0} tagihan ditandai lunas.`);
    }, 'Gagal mencatat pembayaran multi-bulan');
  }

  async function autoGenerateBills() {
    await submit(async () => {
      const response = await api('/tagihan/auto-generate', { method: 'POST', body: { kos_id: activeKosId, days_before_due: 7 } });
      await loadBills();
      await loadOccupants();
      Alert.alert('Tagihan jatuh tempo dibuat', `${response.total || 0} tagihan dibuat/diproses. Setelah berhasil, penghuni akan masuk ke filter Belum bayar.`);
    }, 'Gagal auto-generate tagihan');
  }

  async function generateBillForOccupant(occupant, shareAfter = false) {
    await submit(async () => {
      const body = {
        kos_id: activeKosId,
        kamar_id: Number(occupant.kamar_id),
        bulan: Number(occupant.jatuh_tempo_bulan || thisMonth()),
        tahun: Number(occupant.jatuh_tempo_tahun || thisYear()),
        jumlah_bulan: 1,
      };
      const response = await api('/tagihan/generate', { method: 'POST', body });
      await loadBills();
      await loadOccupants();
      if (shareAfter) {
        await shareOccupantPortal(occupant);
      } else {
        Alert.alert('Tagihan dibuat', `${response.total || 0} tagihan diproses untuk ${occupant.nama_lengkap}.`);
      }
    }, 'Gagal membuat tagihan penghuni');
  }

  function openCashPaymentForOccupant(occupant) {
    setOccupantDetail(null);
    setMultiPayContext(occupant);
    setMultiPayForm({
      ...emptyMultiPayForm,
      kamar_id: String(occupant.kamar_id),
      penghuni_id: String(occupant.id),
      bulan: String(occupant.jatuh_tempo_bulan || thisMonth()),
      tahun: String(occupant.jatuh_tempo_tahun || thisYear()),
      jumlah_bulan: '1',
      metode_pembayaran: 'tunai',
    });
    setModal('multiPay');
  }

  async function updateBillStatus(id, action) {
    if (action === 'verifikasi') {
      Alert.alert('Verifikasi bukti bayar?', 'Tagihan akan berubah menjadi lunas setelah diverifikasi.', [
        { text: 'Batal', style: 'cancel' },
        { text: 'Ya, Verifikasi', onPress: () => updateBillStatusNow(id, action) },
      ]);
      return;
    }
    if (action === 'tolak') {
      Alert.alert('Tolak bukti bayar?', 'Gunakan ini jika bukti tidak valid atau dicurigai palsu.', [
        { text: 'Batal', style: 'cancel' },
        { text: 'Ya, Tolak', style: 'destructive', onPress: () => updateBillStatusNow(id, action) },
      ]);
      return;
    }
    if (action === 'lunas') {
      Alert.alert('Catat bayar tunai?', 'Tagihan akan ditandai lunas dengan metode tunai.', [
        { text: 'Batal', style: 'cancel' },
        { text: 'Ya, Bayar Tunai', onPress: () => updateBillStatusNow(id, action) },
      ]);
      return;
    }
    await updateBillStatusNow(id, action);
  }

  async function updateBillStatusNow(id, action) {
    await submit(async () => {
      const options = action === 'tolak'
        ? { method: 'PUT', body: { alasan_penolakan: 'Bukti pembayaran ditolak oleh pemilik kos.' } }
        : { method: 'PUT' };
      await api(`/tagihan/${id}/${action}`, options);
      await loadBills();
      await loadOccupants();
    }, 'Gagal memperbarui tagihan');
  }

  function openInitialPaymentCorrection(bill) {
    setInitialPaymentForm({
      id: String(bill.id),
      nominal: formatNumberInput(bill.nominal_terbayar || 0),
      tanggal_bayar: bill.tanggal_bayar || today(),
      harga_kamar: Number(bill.nominal || 0),
    });
    setModal('initialPayment');
  }

  function saveInitialPaymentCorrection() {
    const nominal = Number(cleanNumber(initialPaymentForm.nominal));
    if (!initialPaymentForm.tanggal_bayar) return Alert.alert('Tanggal wajib diisi', 'Pilih tanggal DP diterima.');
    if (nominal < 0 || nominal > Number(initialPaymentForm.harga_kamar)) return Alert.alert('Nominal DP tidak sesuai', 'DP tidak boleh melebihi harga sewa kamar.');

    Alert.alert('Simpan koreksi DP?', `DP akan diperbarui menjadi ${money(nominal)}.`, [
      { text: 'Batal', style: 'cancel' },
      {
        text: 'Simpan',
        onPress: () => submit(async () => {
          await api(`/tagihan/${initialPaymentForm.id}/pembayaran-awal`, {
            method: 'PUT',
            body: { nominal, tanggal_bayar: initialPaymentForm.tanggal_bayar },
          });
          setModal(null);
          setInitialPaymentForm(emptyInitialPaymentForm);
          await Promise.all([loadBills(), loadOccupants(), loadMore()]);
        }, 'Gagal mengoreksi DP'),
      },
    ]);
  }

  async function savePaymentMethod() {
    if (paymentForm.jenis === 'bank' && !paymentForm.nama_bank.trim()) return Alert.alert('Bank wajib diisi', 'Pilih transfer manual lalu isi nama bank.');
    if (paymentForm.jenis === 'bank' && !paymentForm.nomor_rekening.trim()) return Alert.alert('Nomor rekening wajib diisi', 'Nomor rekening akan tampil di portal penghuni.');
    if (paymentForm.jenis === 'bank' && !paymentForm.atas_nama.trim()) return Alert.alert('Atas nama wajib diisi', 'Isi nama pemilik rekening.');
    await submit(async () => {
      const body = {
        ...paymentForm,
        kos_id: activeKosId,
        verification_mode: paymentForm.jenis === 'qris' ? 'automatic' : 'manual',
        gateway_provider: paymentForm.jenis === 'qris' ? 'xendit' : null,
        nama_bank: paymentForm.jenis === 'qris' ? 'QRIS Otomatis' : paymentForm.nama_bank,
        instruksi_pembayaran: paymentForm.instruksi_pembayaran || (paymentForm.jenis === 'qris'
          ? 'Scan QRIS. Pembayaran akan terverifikasi otomatis.'
          : 'Transfer sesuai nominal tagihan, lalu unggah bukti pembayaran.'),
      };
      await api('/payment-methods', { method: 'POST', body });
      setModal(null);
      setPaymentForm(emptyPaymentForm);
      await loadMore();
    }, 'Gagal menyimpan metode pembayaran');
  }

  async function saveWithdraw() {
    if (!cleanNumber(withdrawForm.nominal)) return Alert.alert('Nominal wajib diisi', 'Isi nominal saldo yang ingin ditarik.');
    if (!withdrawForm.nama_bank.trim()) return Alert.alert('Bank wajib diisi', 'Isi nama bank tujuan penarikan.');
    if (!withdrawForm.nomor_rekening.trim()) return Alert.alert('Nomor rekening wajib diisi', 'Isi nomor rekening tujuan.');
    if (!withdrawForm.atas_nama.trim()) return Alert.alert('Atas nama wajib diisi', 'Isi nama pemilik rekening.');
    const nominal = Number(cleanNumber(withdrawForm.nominal));

    Alert.alert(
      'Cek rekening tujuan',
      `Pastikan rekening sudah benar:\n\n${withdrawForm.nama_bank}\n${withdrawForm.nomor_rekening}\na.n. ${withdrawForm.atas_nama}\n\nNominal tarik: ${money(nominal)}`,
      [
        { text: 'Periksa Lagi', style: 'cancel' },
        {
          text: 'Ajukan',
          onPress: async () => {
            await submit(async () => {
              await api('/wallet/withdraw', {
                method: 'POST',
                body: {
                  kos_id: activeKosId,
                  nominal,
                  nama_bank: withdrawForm.nama_bank,
                  nomor_rekening: withdrawForm.nomor_rekening,
                  atas_nama: withdrawForm.atas_nama,
                },
              });
              setModal(null);
              setWithdrawForm(emptyWithdrawForm);
              await loadMore();
              Alert.alert('Penarikan diajukan', 'Saldo akan diproses ke rekening tujuan.');
            }, 'Gagal menarik saldo');
          },
        },
      ],
    );
  }

  async function togglePaymentMethod(item) {
    const nextActive = !(Number(item.is_active) === 1 || item.is_active === true);
    await submit(async () => {
      await api(`/payment-methods/${item.id}`, { method: 'PUT', body: { is_active: nextActive } });
      await loadMore();
    }, 'Gagal memperbarui metode pembayaran');
  }

  async function deletePaymentMethod(item) {
    Alert.alert('Hapus metode pembayaran?', 'Metode ini akan dihapus dari daftar. Jika hanya tidak dipakai sementara, pilih Nonaktifkan.', [
      { text: 'Batal', style: 'cancel' },
      {
        text: 'Hapus',
        style: 'destructive',
        onPress: async () => {
          await submit(async () => {
            await api(`/payment-methods/${item.id}`, { method: 'DELETE' });
            await loadMore();
          }, 'Gagal menghapus metode pembayaran');
        },
      },
    ]);
  }

  async function saveFinance() {
    if (!cleanNumber(financeForm.nominal)) return Alert.alert('Nominal wajib diisi', 'Isi angka saja.');
    await submit(async () => {
      const isEdit = Boolean(financeForm.id);
      const body = { ...financeForm, kos_id: activeKosId, nominal: Number(cleanNumber(financeForm.nominal)) };
      delete body.id;
      const response = await api(isEdit ? `/keuangan/${financeForm.id}` : '/keuangan', { method: isEdit ? 'PUT' : 'POST', body });
      const savedDate = response.data?.tanggal || financeForm.tanggal;
      const [savedYear, savedMonth] = String(savedDate).split('-');
      setModal(null);
      setFinanceForm(emptyFinanceForm);
      const nextFilter = { bulan: String(Number(savedMonth || thisMonth())), tahun: savedYear || thisYear() };
      const samePeriod = String(financeFilter.bulan) === nextFilter.bulan && String(financeFilter.tahun) === nextFilter.tahun;
      setFinanceFilter(nextFilter);
      if (samePeriod) {
        const response = await api(`/keuangan?kos_id=${activeKosId}&bulan=${nextFilter.bulan}&tahun=${nextFilter.tahun}`);
        setFinances(response.data || []);
        setFinanceSummary(response.summary || null);
      }
      Alert.alert(isEdit ? 'Transaksi diperbarui' : 'Transaksi tersimpan', `Data tampil pada laporan ${monthName(savedMonth || thisMonth())} ${savedYear || thisYear()}.`);
    }, 'Gagal menyimpan transaksi');
  }

  function openFinanceCreateModal() {
    setFinanceForm({ ...emptyFinanceForm, tanggal: today() });
    setModal('finance');
  }

  function openFinanceEditModal(item) {
    setFinanceForm({
      id: item.id,
      jenis: item.jenis || 'pengeluaran',
      tanggal: item.tanggal || today(),
      nominal: formatNumberInput(item.nominal),
      keterangan: item.keterangan || '',
    });
    setModal('finance');
  }

  function deleteFinance(item) {
    Alert.alert(
      'Hapus transaksi?',
      `${item.jenis === 'pengeluaran' ? 'Pengeluaran' : 'Pemasukan'} ${money(item.nominal)} akan dihapus dari laporan keuangan.`,
      [
        { text: 'Batal', style: 'cancel' },
        {
          text: 'Hapus',
          style: 'destructive',
          onPress: () => submit(async () => {
            await api(`/keuangan/${item.id}`, { method: 'DELETE' });
            await loadMore();
          }, 'Gagal menghapus transaksi'),
        },
      ],
    );
  }

  async function downloadFinancePdf() {
    if (!activeKosId) return Alert.alert('Pilih kos', 'Pilih kos yang ingin dibuat laporannya.');
    await submit(async () => {
      const month = String(financeFilter.bulan).padStart(2, '0');
      const filename = `laporan-keuangan-${activeKosId}-${financeFilter.tahun}-${month}.pdf`;
      const target = `${FileSystem.documentDirectory}${filename}`;
      const url = `${apiBase}/keuangan/laporan-pdf?kos_id=${activeKosId}&bulan=${financeFilter.bulan}&tahun=${financeFilter.tahun}`;
      const result = await FileSystem.downloadAsync(url, target, {
        headers: { Authorization: `Bearer ${tokenValue}`, Accept: 'application/pdf' },
      });

      if (result.status < 200 || result.status >= 300) {
        throw new Error(`Download gagal. Server mengirim status ${result.status}.`);
      }

      if (await Sharing.isAvailableAsync()) {
        await Sharing.shareAsync(result.uri, {
          mimeType: 'application/pdf',
          dialogTitle: 'Simpan atau bagikan laporan keuangan',
          UTI: 'com.adobe.pdf',
        });
      } else {
        Alert.alert('PDF berhasil dibuat', `File tersimpan di ${result.uri}`);
      }
    }, 'Gagal download laporan PDF');
  }

  async function saveAnnouncement() {
    if (!announcementForm.judul.trim() || !announcementForm.isi.trim()) return Alert.alert('Pengumuman belum lengkap', 'Isi judul dan isi pengumuman.');
    await submit(async () => {
      const isEdit = Boolean(announcementForm.id);
      const body = { ...announcementForm, kos_id: activeKosId };
      delete body.id;
      await api(isEdit ? `/pengumuman/${announcementForm.id}` : '/pengumuman', { method: isEdit ? 'PUT' : 'POST', body });
      setModal(null);
      setAnnouncementForm(emptyAnnouncementForm);
      await loadMore();
    }, 'Gagal menyimpan pengumuman');
  }

  function openAnnouncementCreateModal() {
    setAnnouncementForm(emptyAnnouncementForm);
    setModal('announcement');
  }

  function openAnnouncementEditModal(item) {
    setAnnouncementForm({
      id: item.id,
      judul: item.judul || '',
      isi: item.isi || '',
      status: item.status || 'aktif',
    });
    setModal('announcement');
  }

  function deleteAnnouncement(item) {
    Alert.alert(
      'Hapus pengumuman?',
      `Pengumuman "${item.judul}" akan dihapus permanen dan tidak lagi tersedia untuk penghuni.`,
      [
        { text: 'Batal', style: 'cancel' },
        {
          text: 'Hapus',
          style: 'destructive',
          onPress: () => submit(async () => {
            await api(`/pengumuman/${item.id}`, { method: 'DELETE' });
            await loadMore();
          }, 'Gagal menghapus pengumuman'),
        },
      ],
    );
  }

  async function toggleAnnouncement(item) {
    const nextStatus = item.status === 'aktif' ? 'nonaktif' : 'aktif';
    const title = nextStatus === 'aktif' ? 'Aktifkan pengumuman?' : 'Nonaktifkan pengumuman?';
    const message = nextStatus === 'aktif'
      ? 'Pengumuman ini akan tampil kembali untuk penghuni.'
      : 'Pengumuman ini tidak akan ditampilkan lagi ke penghuni, tapi datanya tetap tersimpan.';
    Alert.alert(title, message, [
      { text: 'Batal', style: 'cancel' },
      {
        text: nextStatus === 'aktif' ? 'Aktifkan' : 'Nonaktifkan',
        style: nextStatus === 'aktif' ? 'default' : 'destructive',
        onPress: async () => {
          await submit(async () => {
            await api(`/pengumuman/${item.id}`, { method: 'PUT', body: { status: nextStatus } });
            await loadMore();
          }, 'Gagal memperbarui pengumuman');
        },
      },
    ]);
  }

  async function shareOccupantPortal(occupant) {
    const phone = whatsappNumber(occupant.no_wa);
    if (!phone) {
      Alert.alert('Nomor WhatsApp belum ada', 'Edit data penghuni dan isi nomor WhatsApp terlebih dahulu.');
      return;
    }

    await submit(async () => {
      const response = await api(`/penghuni/${occupant.id}/portal-link`);
      const token = response.data?.portal_token;
      const link = token ? portalUrl(token, apiBase) : response.data?.portal_link;
      const message = `Halo ${occupant.nama_lengkap}, ini link portal pembayaran kos kamu:\n${link}`;
      await Linking.openURL(`https://wa.me/${phone}?text=${encodeURIComponent(message)}`);
    }, 'Gagal membuka WhatsApp');
  }

  async function testPushNotification() {
    await submit(async () => {
      await registerPushToken();
      const response = await api('/push-token/test', { method: 'POST' });
      Alert.alert('Notifikasi uji dikirim', response.message || 'Periksa notifikasi pada perangkat ini.');
    }, 'Notifikasi belum siap');
  }

  async function logout() {
    const pushToken = await AsyncStorage.getItem('push_notification_token');
    if (pushToken) {
      await api('/push-token', { method: 'DELETE', body: { token: pushToken } }).catch(() => null);
      await AsyncStorage.removeItem('push_notification_token');
    }
    const logoutRequest = api('/logout', { method: 'POST' }).catch(() => null);
    resetAccountState();
    setPassword('');
    setRegister({ name: '', email: '', phone: '', password: '', password_confirmation: '' });
    setToken(null);
    setTokenValue(null);
    await AsyncStorage.removeItem('token');
    try {
      await logoutRequest;
    } catch {}
  }

  if (booting) return <Shell><Text style={styles.muted}>Memuat BALIKOS...</Text></Shell>;
  if (!tokenValue) return <AuthScreen authMode={authMode} setAuthMode={setAuthMode} email={email} setEmail={setEmail} password={password} setPassword={setPassword} register={register} setRegister={setRegister} doLogin={doLogin} doRegister={doRegister} doGoogleLogin={doGoogleLogin} loading={loading} googleWebClientId={googleWebClientId} googleAndroidClientId={googleAndroidClientId} />;
  if (kosList.length === 0) return <KosSetup form={kosForm} setForm={setKosForm} saveKos={saveKos} logout={logout} loading={loading} />;

  return (
    <View style={styles.app}>
      <StatusBar style="dark" />
      <LinearGradient colors={['#ffffff', '#eef6ff']} style={styles.header}>
        <View style={styles.brandRow}>
          <Image source={balikosLogo} style={styles.headerLogo} resizeMode="cover" />
          <View style={{ flex: 1 }}>
            <Text style={styles.eyebrow}>BALIKOS</Text>
            <Text style={styles.headerTitle}>{activeKos?.nama_kos || 'Pemilik Kos'}</Text>
          </View>
          <SecondaryButton title="Edit" onPress={openKosEditModal} style={styles.headerEditButton} />
        </View>
      </LinearGradient>
      <ScrollView
        contentContainerStyle={styles.content}
        refreshControl={(
          <RefreshControl
            refreshing={refreshing}
            onRefresh={refreshActiveScreen}
            colors={[colors.gold]}
            tintColor={colors.gold}
            progressBackgroundColor={colors.surface}
          />
        )}
      >
        <KosPicker kosList={kosList} activeKosId={activeKosId} setActiveKosId={changeActiveKos} onAdd={openKosCreateModal} onEdit={openKosEditModal} />
        {tab === 'dashboard' && <Dashboard data={dashboard} occupants={occupants} activeKos={activeKos} setTab={setTab} setOccupantFilter={setOccupantFilter} />}
        {tab === 'kamar' && <RoomsScreen rooms={rooms} apiBase={apiBase} openRoomDetail={openRoomDetail} openRoomModal={openRoomCreateModal} />}
        {tab === 'penghuni' && <OccupantsScreen occupants={occupants} rooms={rooms} bills={bills} filter={occupantFilter} setFilter={setOccupantFilter} openOccupantModal={openOccupantModal} openOccupantDetail={setOccupantDetail} autoGenerateBills={autoGenerateBills} />}
        {tab === 'lainnya' && <MoreScreen screen={moreScreen} setScreen={setMoreScreen} paymentMethods={paymentMethods} paymentWallet={paymentWallet} finances={finances} financeSummary={financeSummary} financeFilter={financeFilter} openPeriodModal={openPeriodModal} openFinanceCreateModal={openFinanceCreateModal} openFinanceEditModal={openFinanceEditModal} deleteFinance={deleteFinance} downloadFinancePdf={downloadFinancePdf} announcements={announcements} openAnnouncementCreateModal={openAnnouncementCreateModal} openAnnouncementEditModal={openAnnouncementEditModal} deleteAnnouncement={deleteAnnouncement} toggleAnnouncement={toggleAnnouncement} togglePaymentMethod={togglePaymentMethod} deletePaymentMethod={deletePaymentMethod} testPushNotification={testPushNotification} setModal={setModal} logout={logout} />}
      </ScrollView>
      <BottomNav tab={tab} setTab={setTab} bottomInset={safeInsets.bottom} />
      <RoomDetailModal room={roomDetail} apiBase={apiBase} onClose={() => setRoomDetail(null)} onAddOccupant={openOccupantModal} onEdit={openRoomEditModal} onChangeStatus={openRoomStatusModal} onCheckout={checkoutOccupant} />
      <OccupantDetailModal occupant={occupantDetail} bills={bills.filter((bill) => Number(bill.penghuni_id) === Number(occupantDetail?.id))} rooms={rooms} apiBase={apiBase} onClose={() => setOccupantDetail(null)} onEdit={openOccupantEditModal} onCheckout={checkoutOccupant} onSharePortal={shareOccupantPortal} onGenerateBill={generateBillForOccupant} onCashPay={openCashPaymentForOccupant} updateBillStatus={updateBillStatus} openImagePreview={setImagePreview} onCorrectInitialPayment={openInitialPaymentCorrection} />
      <ImagePreviewModal uri={imagePreview} onClose={() => setImagePreview(null)} />
      <KosFormModal visible={modal === 'kos'} form={kosForm} setForm={setKosForm} onSave={saveKos} onDelete={deleteKos} onClose={() => { setModal(null); setKosForm(emptyKosForm); }} loading={loading} />
      <RoomFormModal visible={modal === 'room'} form={roomForm} setForm={setRoomForm} apiBase={apiBase} onPick={pickRoomImage} onSave={saveRoom} onClose={() => { setModal(null); setRoomForm(emptyRoomForm); }} loading={loading} />
      <OccupantFormModal visible={modal === 'occupant'} form={occupantForm} setForm={setOccupantForm} rooms={rooms} emptyRooms={emptyRooms} apiBase={apiBase} onPickKtp={pickKtpImage} onSave={saveOccupant} onClose={() => { setModal(null); setOccupantForm(emptyOccupantForm); }} loading={loading} />
      <BillFormModal visible={modal === 'bill'} form={billForm} setForm={setBillForm} rooms={rooms} onSave={generateBills} onClose={() => setModal(null)} loading={loading} />
      <MultiPayModal visible={modal === 'multiPay'} form={multiPayForm} setForm={setMultiPayForm} rooms={rooms} occupant={multiPayContext} onSave={payMultiMonth} onClose={() => { setModal(null); setMultiPayContext(null); setMultiPayForm(emptyMultiPayForm); }} loading={loading} />
      <RoomStatusModal visible={modal === 'roomStatus'} form={roomStatusForm} setForm={setRoomStatusForm} onSave={saveRoomStatus} onClose={() => setModal(null)} loading={loading} />
      <PeriodPickerModal visible={modal === 'period'} draft={periodDraft} setDraft={setPeriodDraft} onSave={applyPeriod} onClose={() => setModal(null)} />
      <PaymentFormModal visible={modal === 'payment'} form={paymentForm} setForm={setPaymentForm} onSave={savePaymentMethod} onClose={() => setModal(null)} loading={loading} />
      <PaymentInfoModal visible={modal === 'paymentInfo'} onClose={() => setModal(null)} />
      <WithdrawModal visible={modal === 'withdraw'} form={withdrawForm} setForm={setWithdrawForm} wallet={paymentWallet} onSave={saveWithdraw} onClose={() => { setModal(null); setWithdrawForm(emptyWithdrawForm); }} loading={loading} />
      <InitialPaymentCorrectionModal visible={modal === 'initialPayment'} form={initialPaymentForm} setForm={setInitialPaymentForm} onSave={saveInitialPaymentCorrection} onClose={() => { setModal(null); setInitialPaymentForm(emptyInitialPaymentForm); }} loading={loading} />
      <FinanceFormModal visible={modal === 'finance'} form={financeForm} setForm={setFinanceForm} onSave={saveFinance} onClose={() => { setModal(null); setFinanceForm(emptyFinanceForm); }} loading={loading} />
      <AnnouncementFormModal visible={modal === 'announcement'} form={announcementForm} setForm={setAnnouncementForm} onSave={saveAnnouncement} onClose={() => { setModal(null); setAnnouncementForm(emptyAnnouncementForm); }} loading={loading} />
    </View>
  );
}

function AuthScreen(props) {
  const googleReady = Platform.OS === 'android'
    ? Boolean(props.googleAndroidClientId)
    : Boolean(props.googleWebClientId);

  return (
    <Shell>
      <View style={styles.authHeaderClean}>
        <View style={styles.authTopRow}>
          <Image source={balikosLogo} style={styles.authLogo} resizeMode="contain" />
          <View style={{ flex: 1 }}>
            <Text style={styles.authBrand}>BALIKOS</Text>
            <Text style={styles.authTagline}>Kelola kos, tenang setiap saat</Text>
          </View>
        </View>
      </View>
      <Segment items={['login', 'register']} labels={{ login: 'Masuk', register: 'Daftar' }} value={props.authMode} onChange={props.setAuthMode} />
      {googleReady ? (
        <>
          <GoogleLoginButton
            webClientId={props.googleWebClientId}
            androidClientId={props.googleAndroidClientId}
            onToken={props.doGoogleLogin}
          />
          <View style={styles.authDivider}>
            <View style={styles.dividerLine} />
            <Text style={styles.dividerText}>atau pakai email</Text>
            <View style={styles.dividerLine} />
          </View>
        </>
      ) : null}
      {props.authMode === 'login' ? (
        <>
          <FormField label="Email" value={props.email} onChangeText={props.setEmail} keyboardType="email-address" />
          <FormField label="Password" value={props.password} onChangeText={props.setPassword} secureTextEntry />
          <PrimaryButton title="Masuk" onPress={props.doLogin} loading={props.loading} />
        </>
      ) : (
        <>
          <FormField label="Nama pemilik" value={props.register.name} onChangeText={(v) => props.setRegister({ ...props.register, name: v })} placeholder="Contoh: Made Pemilik Kos" />
          <FormField label="Email" value={props.register.email} onChangeText={(v) => props.setRegister({ ...props.register, email: v })} keyboardType="email-address" />
          <FormField label="Nomor WhatsApp" value={props.register.phone} onChangeText={(v) => props.setRegister({ ...props.register, phone: v })} keyboardType="phone-pad" />
          <FormField label="Password" value={props.register.password} onChangeText={(v) => props.setRegister({ ...props.register, password: v })} secureTextEntry helperText="Minimal 8 karakter." />
          <FormField label="Ulangi password" value={props.register.password_confirmation} onChangeText={(v) => props.setRegister({ ...props.register, password_confirmation: v })} secureTextEntry />
          <PrimaryButton title="Daftar dan Masuk" onPress={props.doRegister} loading={props.loading} />
        </>
      )}
    </Shell>
  );
}

function GoogleLoginButton({ webClientId, androidClientId, onToken }) {
  const googleRedirectUri = AuthSession.makeRedirectUri({
    native: `${APP_SCHEME}:/oauthredirect`,
    scheme: APP_SCHEME,
    path: 'oauthredirect',
  });
  const requestConfig = Platform.OS === 'android'
    ? { androidClientId, redirectUri: googleRedirectUri, ...(webClientId ? { webClientId } : {}) }
    : { webClientId, redirectUri: googleRedirectUri, ...(androidClientId ? { androidClientId } : {}) };
  const [googleRequest, response, promptGoogleLogin] = Google.useIdTokenAuthRequest(requestConfig);

  useEffect(() => {
    if (!response) return;
    if (response.type === 'dismiss' || response.type === 'cancel') {
      return;
    }
    if (response.type === 'error') {
      Alert.alert('Login Google gagal', response.error?.message || 'Google belum bisa mengembalikan login ke aplikasi BALIKOS.');
      return;
    }
    if (response.type !== 'success') return;
    const idToken = response.params?.id_token;
    if (!idToken) {
      Alert.alert('Login Google gagal', 'Token Google tidak diterima. Silakan coba lagi.');
      return;
    }
    onToken(idToken);
  }, [response]);

  return (
    <Pressable disabled={!googleRequest} onPress={() => promptGoogleLogin()} style={({ pressed }) => [styles.googleButton, pressed && styles.pressed, !googleRequest && styles.disabledButton]}>
      <Image source={googleLogo} style={styles.googleIcon} resizeMode="contain" />
      <Text style={styles.googleButtonText}>Masuk dengan Google</Text>
    </Pressable>
  );
}

function KosSetup({ form, setForm, saveKos, logout, loading }) {
  return (
    <Shell>
      <LinearGradient colors={['#052f78', '#0a63c7']} style={styles.authHero}>
        <Image source={balikosLogo} style={styles.heroLogo} resizeMode="contain" />
        <Text style={[styles.eyebrow, styles.heroEyebrow]}>Setup Awal</Text>
        <Text style={styles.heroTitle}>Buat Kos</Text>
        <Text style={styles.heroText}>Pemilik baru perlu membuat data kos dulu, lalu kamar dan penghuni bisa ditambahkan.</Text>
      </LinearGradient>
      <FormField label="Nama kos" value={form.nama_kos} onChangeText={(v) => setForm({ ...form, nama_kos: v })} placeholder="Contoh: Kos Melati Denpasar" />
      <FormField label="Alamat lengkap" value={form.alamat} onChangeText={(v) => setForm({ ...form, alamat: v })} multiline />
      <FormField label="Kecamatan" value={form.kecamatan} onChangeText={(v) => setForm({ ...form, kecamatan: v })} placeholder="Contoh: Denpasar Selatan" />
      <FormField label="Desa/Kelurahan" value={form.desa} onChangeText={(v) => setForm({ ...form, desa: v })} />
      <FormField label="Banjar" value={form.banjar} onChangeText={(v) => setForm({ ...form, banjar: v })} />
      <FormField label="Nomor WhatsApp kos" value={form.no_wa} onChangeText={(v) => setForm({ ...form, no_wa: v })} keyboardType="phone-pad" />
      <PrimaryButton title="Simpan Kos" onPress={saveKos} loading={loading} />
      <SecondaryButton title="Logout" onPress={logout} style={{ marginTop: spacing.md }} />
    </Shell>
  );
}

function KosFormModal({ visible, form, setForm, onSave, onDelete, onClose, loading }) {
  const isEdit = Boolean(form.id);
  return (
    <BaseModal visible={visible} title={isEdit ? 'Edit Kos' : 'Tambah Kos'} onClose={onClose}>
      <Text style={styles.muted}>{isEdit ? 'Perbarui data kos jika ada nama, alamat, atau kontak yang salah.' : 'Isi data kos baru. Setelah disimpan, kos ini otomatis menjadi kos aktif.'}</Text>
      <FormField label="Nama kos" value={form.nama_kos} onChangeText={(v) => setForm({ ...form, nama_kos: v })} placeholder="Contoh: Kos Melati Denpasar" />
      <FormField label="Alamat" value={form.alamat} onChangeText={(v) => setForm({ ...form, alamat: v })} multiline />
      <FormField label="Kecamatan" value={form.kecamatan} onChangeText={(v) => setForm({ ...form, kecamatan: v })} placeholder="Contoh: Denpasar Selatan" />
      <FormField label="Desa" value={form.desa} onChangeText={(v) => setForm({ ...form, desa: v })} />
      <FormField label="Banjar" value={form.banjar} onChangeText={(v) => setForm({ ...form, banjar: v })} />
      <FormField label="WhatsApp kos" value={form.no_wa} onChangeText={(v) => setForm({ ...form, no_wa: v })} keyboardType="phone-pad" />
      <FooterButtons onClose={onClose} onSave={onSave} loading={loading} saveTitle={isEdit ? 'Simpan Perubahan' : 'Simpan Kos'} />
      {isEdit ? <SecondaryButton title="Hapus Kos" onPress={onDelete} style={[styles.cardButton, styles.dangerButton]} /> : null}
    </BaseModal>
  );
}

function RoomsScreen({ rooms, apiBase, openRoomDetail, openRoomModal }) {
  const [search, setSearch] = useState('');
  const keyword = search.trim().toLowerCase();
  const emptyCount = rooms.filter((room) => room.status === 'kosong').length;
  const occupiedCount = rooms.filter((room) => room.status === 'terisi').length;
  const maintenanceCount = rooms.filter((room) => room.status === 'maintenance').length;
  const filteredRooms = rooms.filter((room) => {
    if (!keyword) return true;
    return [
      room.nomor_kamar,
      room.tipe_kamar,
      room.status,
      room.catatan,
      facilityLabels(room).join(' '),
    ].filter(Boolean).some((value) => String(value).toLowerCase().includes(keyword));
  });

  return (
    <>
      <HeaderAction title="Kamar" action="+ Kamar" onPress={openRoomModal} />
      <View style={styles.roomSummaryRow}>
        <MiniSummary label="Kosong" value={emptyCount} />
        <MiniSummary label="Terisi" value={occupiedCount} />
        <MiniSummary label="Perbaikan" value={maintenanceCount} />
      </View>
      <FormField label="Cari kamar" value={search} onChangeText={setSearch} placeholder="Nomor, tipe, status, fasilitas" />
      <Notice text="Klik kartu kamar untuk melihat detail, mengedit fasilitas/foto, mengubah status, atau menambah penghuni." />
      {filteredRooms.map((room) => <RoomCard key={room.id} room={room} apiBase={apiBase} onPress={() => openRoomDetail(room.id)} />)}
      {rooms.length === 0 ? <Empty text="Belum ada kamar." /> : null}
      {rooms.length > 0 && filteredRooms.length === 0 ? <Empty text="Tidak ada kamar yang cocok dengan pencarian." /> : null}
    </>
  );
}

function MiniSummary({ label, value, compact = false }) {
  return (
    <View style={[styles.miniSummary, compact && styles.miniSummaryCompact]}>
      <Text style={styles.miniSummaryValue}>{value}</Text>
      <Text style={styles.miniSummaryLabel} numberOfLines={2}>{label}</Text>
    </View>
  );
}

function OccupantsScreen({ occupants, rooms, bills, filter, setFilter, openOccupantModal, openOccupantDetail, autoGenerateBills }) {
  const [search, setSearch] = useState('');
  const keyword = search.trim().toLowerCase();
  const activeCount = occupants.filter((item) => item.status === 'aktif').length;
  const unpaidCount = occupants.filter((item) => Number(item.tagihan_aktif_count || 0) > 0).length;
  const verifyCount = occupants.filter((item) => Number(item.tagihan_verifikasi_count || 0) > 0).length;
  const dueSoonCount = occupants.filter((item) => item.akan_jatuh_tempo).length;
  const exitedDebtCount = occupants.filter((item) => item.status === 'keluar' && (Number(item.tagihan_aktif_count || 0) > 0 || Number(item.tagihan_verifikasi_count || 0) > 0)).length;
  const filteredOccupants = occupants.filter((item) => {
    const hasUnresolvedBill = Number(item.tagihan_aktif_count || 0) > 0 || Number(item.tagihan_verifikasi_count || 0) > 0;
    if (filter === 'semua' && item.status === 'keluar' && !hasUnresolvedBill) return false;
    if (filter === 'tagihan' && Number(item.tagihan_aktif_count || 0) <= 0) return false;
    if (filter === 'verifikasi' && Number(item.tagihan_verifikasi_count || 0) <= 0) return false;
    if (filter === 'jatuh_tempo' && !item.akan_jatuh_tempo) return false;
    if (filter === 'keluar' && item.status !== 'keluar') return false;
    if (!keyword) return true;
    const room = roomName(rooms, item.kamar_id);
    return [
      item.nama_lengkap,
      item.no_wa,
      item.no_ktp,
      item.pekerjaan,
      item.no_kendaraan,
      item.catatan_pemilik,
      Number(item.tagihan_aktif_count) > 0 ? 'tagihan belum bayar' : '',
      Number(item.tagihan_verifikasi_count) > 0 ? 'perlu verifikasi bukti bayar' : '',
      item.status === 'keluar' ? 'sudah keluar mantan penghuni' : '',
      item.status === 'keluar' && hasUnresolvedBill ? 'sudah keluar masih ada tunggakan' : '',
      item.akan_jatuh_tempo ? 'akan jatuh tempo' : '',
      room,
    ].filter(Boolean).some((value) => String(value).toLowerCase().includes(keyword));
  }).sort((a, b) => {
    const exitDebtA = a.status === 'keluar' && (Number(a.tagihan_aktif_count || 0) > 0 || Number(a.tagihan_verifikasi_count || 0) > 0) ? 2 : 0;
    const exitDebtB = b.status === 'keluar' && (Number(b.tagihan_aktif_count || 0) > 0 || Number(b.tagihan_verifikasi_count || 0) > 0) ? 2 : 0;
    const priorityA = Number(a.tagihan_verifikasi_count || 0) * 5 + Number(a.tagihan_aktif_count || 0) * 3 + exitDebtA + (a.akan_jatuh_tempo ? 1 : 0);
    const priorityB = Number(b.tagihan_verifikasi_count || 0) * 5 + Number(b.tagihan_aktif_count || 0) * 3 + exitDebtB + (b.akan_jatuh_tempo ? 1 : 0);
    return priorityB - priorityA || String(a.nama_lengkap || '').localeCompare(String(b.nama_lengkap || ''));
  });

  return (
    <>
      <HeaderAction title="Penghuni & Pembayaran" action="+ Penghuni" onPress={() => openOccupantModal()} />
      <View style={styles.occupantSummaryGrid}>
        <MiniSummary label="Aktif" value={activeCount} compact />
        <MiniSummary label="Belum bayar" value={unpaidCount} compact />
        <MiniSummary label="Perlu cek" value={verifyCount} compact />
        <MiniSummary label="Keluar nunggak" value={exitedDebtCount} compact />
      </View>
      <View style={styles.compactActionPanel}>
        <Text style={styles.cardTitle}>Alur pembayaran</Text>
        <Text style={styles.helperText}>Klik penghuni untuk cek tagihan, catat bayar tunai, verifikasi bukti, atau share portal. Penghuni keluar yang masih nunggak tetap muncul di sini.</Text>
        {dueSoonCount > 0 ? <SecondaryButton title={`Buat ${dueSoonCount} Tagihan 7 Hari Ini`} onPress={autoGenerateBills} style={{ marginTop: spacing.sm }} /> : null}
      </View>
      <FilterChips items={['semua', 'tagihan', 'verifikasi', 'jatuh_tempo', 'keluar']} labels={{ semua: 'Aktif+', tagihan: 'Belum bayar', verifikasi: 'Perlu cek', jatuh_tempo: 'Jatuh tempo', keluar: 'Keluar' }} value={filter} onChange={setFilter} />
      <FormField label="Cari penghuni" value={search} onChangeText={setSearch} placeholder="Nama, kamar, WA, kendaraan" />
      {filteredOccupants.map((item) => <OccupantCard key={item.id} item={item} bills={bills.filter((bill) => Number(bill.penghuni_id) === Number(item.id))} rooms={rooms} onPress={() => openOccupantDetail(item)} />)}
      {occupants.length === 0 ? <Empty text="Belum ada penghuni." /> : null}
      {occupants.length > 0 && filteredOccupants.length === 0 ? <Empty text="Tidak ada penghuni yang cocok dengan pencarian." /> : null}
    </>
  );
}

function BillsScreen({ bills, rooms, apiBase, openGenerate, openMultiPay, autoGenerateBills, updateBillStatus, openImagePreview }) {
  const [filter, setFilter] = useState('perlu_cek');
  const [search, setSearch] = useState('');
  const keyword = search.trim().toLowerCase();
  const reviewCount = bills.filter((bill) => bill.status === 'menunggu_verifikasi').length;
  const unpaidCount = bills.filter((bill) => billIsUnpaid(bill)).length;
  const paidCount = bills.filter((bill) => bill.status === 'lunas').length;
  const sortedBills = [...bills].sort((a, b) => {
    const statusDiff = billSortPriority(a.status) - billSortPriority(b.status);
    if (statusDiff !== 0) return statusDiff;
    return Number(a.tahun) - Number(b.tahun) || Number(a.bulan) - Number(b.bulan) || Number(a.id) - Number(b.id);
  }).filter((bill) => {
    if (filter === 'perlu_cek' && bill.status !== 'menunggu_verifikasi') return false;
    if (filter === 'belum_lunas' && !billIsUnpaid(bill)) return false;
    if (!keyword) return true;
    return [
      roomName(rooms, bill.kamar_id),
      monthName(bill.bulan),
      bill.tahun,
      billStatusLabel(bill.status),
      bill.nominal,
    ].filter(Boolean).some((value) => String(value).toLowerCase().includes(keyword));
  });

  return (
    <>
      <Text style={styles.sectionTitle}>Tagihan</Text>
      <View style={styles.roomSummaryRow}>
        <MiniSummary label="Perlu Cek" value={reviewCount} />
        <MiniSummary label="Belum Lunas" value={unpaidCount} />
        <MiniSummary label="Lunas" value={paidCount} />
      </View>
      <View style={styles.compactActionPanel}>
        <PrimaryButton title="Catat Pembayaran" onPress={openMultiPay} />
        <View style={styles.actionRow}>
          <SecondaryButton title="Buat Otomatis" onPress={autoGenerateBills} style={styles.flexButton} />
          <SecondaryButton title="Buat Manual" onPress={openGenerate} style={styles.flexButton} />
        </View>
        <Text style={styles.helperText}>Link portal dibagikan dari detail penghuni. Tagihan terbaru akan muncul otomatis di link yang sama.</Text>
      </View>
      <Text style={styles.sectionTitle}>Daftar Tagihan</Text>
      <Segment items={['perlu_cek', 'belum_lunas', 'semua']} labels={{ perlu_cek: 'Perlu cek', belum_lunas: 'Belum lunas', semua: 'Semua' }} value={filter} onChange={setFilter} />
      <FormField label="Cari tagihan" value={search} onChangeText={setSearch} placeholder="Kamar, bulan, status" />
      {sortedBills.map((bill) => <BillCard key={bill.id} bill={bill} rooms={rooms} apiBase={apiBase} updateBillStatus={updateBillStatus} openImagePreview={openImagePreview} />)}
      {bills.length === 0 ? <Empty text="Belum ada tagihan." /> : null}
      {bills.length > 0 && sortedBills.length === 0 ? <Empty text="Tidak ada tagihan pada filter ini." /> : null}
    </>
  );
}

function MoreScreen({ screen, setScreen, paymentMethods, paymentWallet, finances, financeSummary, financeFilter, openPeriodModal, openFinanceCreateModal, openFinanceEditModal, deleteFinance, downloadFinancePdf, announcements, openAnnouncementCreateModal, openAnnouncementEditModal, deleteAnnouncement, toggleAnnouncement, togglePaymentMethod, deletePaymentMethod, testPushNotification, setModal, logout }) {
  const activeMethod = paymentMethods.find((item) => Number(item.is_active) === 1 || item.is_active === true);
  return (
    <>
      <Text style={styles.sectionTitle}>Menu Lainnya</Text>
      <Segment items={['payment', 'finance', 'announcement', 'help']} labels={{ payment: 'Metode', finance: 'Keuangan', announcement: 'Info', help: 'Bantuan' }} value={screen} onChange={setScreen} />
      {screen === 'payment' && (
        <>
          <View style={styles.rowBetween}>
            <View style={styles.titleWithInfo}>
              <Text style={styles.sectionTitle}>Metode Pembayaran</Text>
              <InfoButton onPress={() => setModal('paymentInfo')} />
            </View>
            <SecondaryButton title="+ Pilih" onPress={() => setModal('payment')} style={styles.smallButton} />
          </View>
          <PaymentFlowSummary method={activeMethod} />
          <QrisWalletCard wallet={paymentWallet} onWithdraw={() => setModal('withdraw')} />
          {paymentMethods.map((item) => <PaymentMethodCard key={item.id} item={item} onToggle={() => togglePaymentMethod(item)} onDelete={() => deletePaymentMethod(item)} />)}
          {paymentMethods.length === 0 ? <Empty text="Belum ada metode pembayaran. Pilih QRIS otomatis atau transfer bank manual." /> : null}
        </>
      )}
      {screen === 'finance' && (
        <>
          <HeaderAction title="Keuangan" action="+ Transaksi" onPress={openFinanceCreateModal} />
          <Pressable onPress={openPeriodModal} style={({ pressed }) => [styles.periodButton, pressed && styles.pressed]}>
            <Text style={styles.periodLabel}>Periode laporan</Text>
            <Text style={styles.periodValue}>{monthName(financeFilter.bulan)} {financeFilter.tahun}</Text>
          </Pressable>
          <SecondaryButton title="Download PDF" onPress={downloadFinancePdf} style={{ marginBottom: spacing.md }} />
          <ProfitSummary summary={financeSummary} />
          <Text style={styles.sectionTitle}>Transaksi Manual</Text>
          <Text style={styles.muted}>Daftar ini berisi pemasukan/pengeluaran yang pemilik input sendiri, misalnya listrik, air, kebersihan, perbaikan, deposit, atau denda.</Text>
          {finances.map((item) => <FinanceCard key={item.id} item={item} onEdit={() => openFinanceEditModal(item)} onDelete={() => deleteFinance(item)} />)}
          {finances.length === 0 ? <Empty text="Belum ada transaksi manual pada periode ini." /> : null}
        </>
      )}
      {screen === 'announcement' && (
        <>
          <HeaderAction title="Pengumuman" action="+ Info" onPress={openAnnouncementCreateModal} />
          {announcements.map((item) => <AnnouncementCard key={item.id} item={item} onToggle={() => toggleAnnouncement(item)} onEdit={() => openAnnouncementEditModal(item)} onDelete={() => deleteAnnouncement(item)} />)}
          {announcements.length === 0 ? <Empty text="Belum ada pengumuman." /> : null}
        </>
      )}
      {screen === 'help' && <HelpScreen testPushNotification={testPushNotification} />}
      <SecondaryButton title="Logout" onPress={logout} style={{ marginTop: spacing.lg }} />
    </>
  );
}

function HelpScreen({ testPushNotification }) {
  const openEmail = () => {
    Linking.openURL('mailto:admin.balisantih@gmail.com?subject=Bantuan%20Aplikasi%20BALIKOS').catch(() => {
      Alert.alert('Email bantuan', 'Silakan hubungi admin.balisantih@gmail.com');
    });
  };
  const steps = [
    ['1', 'Buat data kos', 'Lengkapi nama kos dan alamat. Jika punya beberapa kos, pilih kos yang ingin dikelola dari bagian atas aplikasi.'],
    ['2', 'Tambah kamar', 'Isi nomor kamar, harga sewa, fasilitas, status kamar, dan foto kamar agar mudah dikenali.'],
    ['3', 'Tambah penghuni', 'Masukkan penghuni ke kamar kosong. Tanggal jatuh tempo dibuat otomatis dari tanggal masuk.'],
    ['4', 'Kelola pembayaran', 'Buka menu Penghuni untuk melihat yang belum bayar, perlu dicek, atau akan jatuh tempo. Pembayaran tunai bisa langsung dicatat dari nama penghuni.'],
    ['5', 'Pantau keuangan', 'Di menu Keuangan, pilih bulan laporan, catat pemasukan atau pengeluaran lain, lalu download PDF jika perlu.'],
    ['6', 'Bagikan portal penghuni', 'Gunakan link portal penghuni agar penghuni bisa melihat tagihan, info kos, dan mengirim bukti pembayaran.'],
  ];

  return (
    <>
      <Text style={styles.sectionTitle}>Bantuan</Text>
      <View style={styles.helpHero}>
        <Text style={styles.helpTitle}>Panduan singkat BALIKOS</Text>
        <Text style={styles.muted}>Ikuti alur ini agar data kos, kamar, penghuni, pembayaran, dan laporan keuangan tersusun rapi.</Text>
      </View>
      {steps.map(([number, title, description]) => (
        <View key={number} style={styles.helpStep}>
          <View style={styles.helpStepNumber}><Text style={styles.helpStepNumberText}>{number}</Text></View>
          <View style={{ flex: 1 }}>
            <Text style={styles.helpStepTitle}>{title}</Text>
            <Text style={styles.muted}>{description}</Text>
          </View>
        </View>
      ))}
      <View style={styles.helpContactCard}>
        <Text style={styles.cardTitle}>Notifikasi</Text>
        <Text style={styles.muted}>Pastikan pengingat pembayaran dapat muncul sebelum aplikasi dibuka.</Text>
        <SecondaryButton title="Uji Notifikasi" onPress={testPushNotification} style={{ marginTop: spacing.sm, marginBottom: spacing.lg }} />
        <Text style={styles.cardTitle}>Butuh bantuan?</Text>
        <Text style={styles.muted}>Jika mengalami kendala saat memakai aplikasi, hubungi admin BALIKOS melalui email berikut.</Text>
        <Pressable onPress={openEmail} style={({ pressed }) => [styles.emailButton, pressed && styles.pressed]}>
          <Text style={styles.emailButtonText}>admin.balisantih@gmail.com</Text>
        </Pressable>
      </View>
    </>
  );
}

function RoomDetailModal({ room, apiBase, onClose, onAddOccupant, onEdit, onChangeStatus, onCheckout }) {
  const [hiddenPhotoKeys, setHiddenPhotoKeys] = useState([]);
  const allPhotos = room ? roomPhotos(room, apiBase) : [];
  const photoSignature = allPhotos.map((photo) => photo.key).join('|');
  useEffect(() => {
    setHiddenPhotoKeys([]);
  }, [room?.id, photoSignature]);

  if (!room) return null;
  const penghuni = room.penghuni_aktif;
  const canAdd = !penghuni && room.status === 'kosong';
  const labels = facilityLabels(room);
  const photos = allPhotos.filter((photo) => !hiddenPhotoKeys.includes(photo.key));
  return (
    <Modal visible transparent animationType="slide" onRequestClose={onClose}>
      <View style={styles.modalOverlay}>
        <ScrollView
          style={styles.modalCard}
          contentContainerStyle={styles.modalCardContent}
          scrollIndicatorInsets={{ bottom: 120 }}
        >
          <Text style={styles.modalTitle}>Kamar {room.nomor_kamar}</Text>
          <Text style={styles.muted}>{room.tipe_kamar || '-'} - {money(room.harga_bulanan)} - {room.status}</Text>
          {photos.length ? (
            <View style={styles.roomPhotoGrid}>
              {photos.map((photo, index) => (
                <View key={photo.key || index} style={styles.roomPhotoItem}>
                  <RoomPhotoImage photo={photo} onFail={() => setHiddenPhotoKeys((keys) => [...new Set([...keys, photo.key])])} />
                </View>
              ))}
            </View>
          ) : <View style={styles.emptyPhoto}><Text style={styles.muted}>Belum ada foto kamar.</Text></View>}
          <Text style={styles.sectionTitle}>Fasilitas</Text>
          {labels.length ? <View style={styles.facilityChipRow}>{labels.map((label) => <Text key={label} style={styles.facilityChip}>{label}</Text>)}</View> : <Text style={styles.muted}>Belum ada fasilitas dipilih.</Text>}
          <Text style={styles.sectionTitle}>Penghuni Aktif</Text>
          {penghuni ? <SimpleCard title={penghuni.nama_lengkap} lines={[`WA ${penghuni.no_wa || '-'}`, `Masuk ${penghuni.tanggal_masuk || '-'}`, penghuni.pekerjaan || '-']} /> : <Empty text="Kamar belum terisi." />}
          {canAdd ? <PrimaryButton title="Tambah Penghuni di Kamar Ini" onPress={() => onAddOccupant(room)} style={{ marginTop: spacing.md }} /> : null}
          {penghuni ? <SecondaryButton title="Keluarkan Penghuni" onPress={() => onCheckout(penghuni)} style={{ marginTop: spacing.md }} /> : null}
          <SecondaryButton title="Edit Kamar & Fasilitas" onPress={() => onEdit(room)} style={{ marginTop: spacing.md }} />
          {!penghuni ? <SecondaryButton title="Ubah Status Kamar" onPress={() => onChangeStatus(room)} style={{ marginTop: spacing.md }} /> : null}
          <SecondaryButton title="Tutup" onPress={onClose} style={{ marginTop: spacing.md }} />
        </ScrollView>
      </View>
    </Modal>
  );
}

function OccupantDetailModal({ occupant, bills, rooms, apiBase, onClose, onEdit, onCheckout, onSharePortal, onGenerateBill, onCashPay, updateBillStatus, openImagePreview, onCorrectInitialPayment }) {
  if (!occupant) return null;
  const sortedBills = [...(bills || [])].sort((a, b) => billSortPriority(a.status) - billSortPriority(b.status) || Number(b.tahun) - Number(a.tahun) || Number(b.bulan) - Number(a.bulan));
  const hasActiveBill = sortedBills.some((bill) => bill.status !== 'lunas');
  const isExited = occupant.status === 'keluar';
  const recordedLoss = sortedBills.reduce((total, bill) => total + Number(bill.kerugian_tunggakan || 0), 0);
  const canCollectPayment = !isExited || hasActiveBill;
  return (
    <Modal visible transparent animationType="slide" onRequestClose={onClose}>
      <View style={styles.modalOverlay}>
        <ScrollView
          style={styles.modalCard}
          contentContainerStyle={styles.modalCardContent}
          scrollIndicatorInsets={{ bottom: 120 }}
        >
          <Text style={styles.modalTitle}>{occupant.nama_lengkap}</Text>
          <Text style={styles.muted}>Kamar {roomName(rooms, occupant.kamar_id)} - Status {occupant.status}</Text>
          <Text style={styles.sectionTitle}>Pembayaran</Text>
          {occupant.akan_jatuh_tempo && !hasActiveBill ? (
            <View style={styles.notice}>
              <Text style={styles.noticeText}>Penghuni ini akan jatuh tempo pada {occupant.jatuh_tempo_berikutnya}. Buat tagihan, lalu share portal ke penghuni.</Text>
            </View>
          ) : null}
          {isExited && hasActiveBill ? (
            <View style={styles.notice}>
              <Text style={styles.noticeText}>{recordedLoss > 0 ? `Penghuni ini sudah keluar. Sisa tunggakan ${money(recordedLoss)} sudah masuk sebagai kerugian laporan. Jika kemudian dibayar, pembayaran tetap tercatat sebagai pemasukan sewa.` : 'Penghuni ini sudah keluar, tapi masih ada tagihan yang belum selesai. Pemilik tetap bisa share portal atau mencatat pembayaran tunai.'}</Text>
            </View>
          ) : null}
          {canCollectPayment ? (
            <View style={styles.actionRow}>
              <PrimaryButton title="Kirim ke WA" onPress={() => onSharePortal(occupant)} style={styles.flexButton} />
              <SecondaryButton title="Bayar Tunai" onPress={() => onCashPay(occupant)} style={styles.flexButton} />
            </View>
          ) : (
            <View style={styles.notice}>
              <Text style={styles.noticeText}>Penghuni ini sudah keluar dan tidak memiliki tunggakan aktif.</Text>
            </View>
          )}
          {occupant.status === 'aktif' && occupant.akan_jatuh_tempo ? (
            <SecondaryButton title="Buat Tagihan Jatuh Tempo" onPress={() => onGenerateBill(occupant)} style={{ marginTop: spacing.sm }} />
          ) : null}
          {sortedBills.map((bill) => <OccupantBillCard key={bill.id} bill={bill} apiBase={apiBase} updateBillStatus={updateBillStatus} openImagePreview={openImagePreview} onCorrectInitialPayment={onCorrectInitialPayment} />)}
          {sortedBills.length === 0 ? <Empty text="Belum ada tagihan untuk penghuni ini." /> : null}
          <Text style={styles.sectionTitle}>Kontak</Text>
          <SimpleCard title="Data Kontak" lines={[`WA ${occupant.no_wa || '-'}`, `KTP ${occupant.no_ktp || '-'}`, `Kontak darurat ${occupant.kontak_darurat || '-'}`]} />
          {occupant.foto_ktp ? (
            <>
              <Text style={styles.label}>Foto KTP</Text>
              <Image source={{ uri: storageUrl(occupant.foto_ktp, apiBase) }} style={styles.proofImage} />
            </>
          ) : null}
          <Text style={styles.sectionTitle}>Masa Tinggal</Text>
          <SimpleCard title="Tanggal" lines={[`Masuk ${occupant.tanggal_masuk || '-'}`, `Keluar ${occupant.tanggal_keluar || '-'}`]} />
          <Text style={styles.sectionTitle}>Lainnya</Text>
          <SimpleCard title="Informasi Penghuni" lines={[occupant.pekerjaan || 'Pekerjaan -', occupant.alamat_asal || 'Alamat asal -', occupant.no_kendaraan || 'Kendaraan -']} />
          {occupant.catatan_pemilik ? <SimpleCard title="Catatan Pemilik (Pribadi)" lines={[occupant.catatan_pemilik]} /> : null}
          <SecondaryButton title="Edit Data Penghuni" onPress={() => onEdit(occupant)} style={{ marginTop: spacing.md }} />
          {occupant.status === 'aktif' ? <SecondaryButton title="Keluarkan Penghuni" onPress={() => onCheckout(occupant)} style={{ marginTop: spacing.md }} /> : null}
          <SecondaryButton title="Tutup" onPress={onClose} style={{ marginTop: spacing.md }} />
        </ScrollView>
      </View>
    </Modal>
  );
}

function RoomFormModal({ visible, form, setForm, apiBase, onPick, onSave, onClose, loading }) {
  const isEdit = Boolean(form.id);
  const existingPhotos = (form.existing_fotos || []).map((photo, index) => ({
    key: `existing-${photo.id || photo.path || index}`,
    uri: storageUrl(photo.path || photo.url, apiBase),
    sources: roomPhotoSources(photo, apiBase),
    id: photo.id,
    existing: true,
  }));
  const newPhotos = (form.fotos || []).map((photo, index) => ({
    key: `new-${photo.uri}-${index}`,
    uri: photo.uri,
    sources: [photo.uri],
    index,
    existing: false,
  }));
  const photos = [...existingPhotos, ...newPhotos];
  const canAddPhoto = photos.length < 5;
  const removePhoto = (photo) => {
    if (photo.existing) {
      setForm({
        ...form,
        existing_fotos: (form.existing_fotos || []).filter((item) => item.id !== photo.id),
        hapus_foto_ids: photo.id ? [...(form.hapus_foto_ids || []), photo.id] : form.hapus_foto_ids,
      });
      return;
    }
    setForm({ ...form, fotos: (form.fotos || []).filter((_, index) => index !== photo.index) });
  };
  return (
    <BaseModal visible={visible} title={isEdit ? 'Edit Kamar' : 'Tambah Kamar'} onClose={onClose}>
      <Text style={styles.muted}>Isi data yang biasanya dilihat penghuni: nomor kamar, harga, status, foto, dan fasilitas.</Text>
      <FormField label="Nomor kamar" value={form.nomor_kamar} onChangeText={(v) => setForm({ ...form, nomor_kamar: v })} placeholder="Contoh: A1, B-02, 101" />
      <FormField label="Tipe kamar" value={form.tipe_kamar} onChangeText={(v) => setForm({ ...form, tipe_kamar: v })} placeholder="Standard, Deluxe, AC" />
      <FormField label="Harga bulanan" value={form.harga_bulanan} onChangeText={(v) => setForm({ ...form, harga_bulanan: formatNumberInput(v) })} keyboardType="number-pad" placeholder="1.200.000" helperText={form.harga_bulanan ? money(form.harga_bulanan) : 'Isi angka saja.'} />
      <Text style={styles.label}>Status kamar</Text>
      {form.has_active_occupant ? (
        <View style={styles.lockedInfo}>
          <Text style={styles.lockedTitle}>Terisi</Text>
          <Text style={styles.muted}>Status mengikuti penghuni aktif dan tidak dapat diubah dari form kamar.</Text>
        </View>
      ) : (
        <Segment items={['kosong', 'maintenance']} labels={{ kosong: 'Kosong', maintenance: 'Maintenance' }} value={form.status} onChange={(status) => setForm({ ...form, status })} />
      )}
      <Text style={styles.label}>Fasilitas</Text>
      <Text style={styles.muted}>Aktifkan fasilitas yang tersedia di kamar ini.</Text>
      {facilityRows.map(([key, label]) => <ToggleRow key={key} label={label} value={form[key]} onValueChange={(value) => setForm({ ...form, [key]: value })} />)}
      <FormField label="Catatan" value={form.catatan} onChangeText={(v) => setForm({ ...form, catatan: v })} multiline placeholder="Contoh: Harga kamar sudah termasuk air. Listrik dibayar terpisah." />
      <Text style={styles.label}>Foto kamar</Text>
      <Text style={styles.muted}>Maksimal 5 foto. Foto akan dikompresi agar aplikasi tetap ringan.</Text>
      {photos.length ? (
        <View style={styles.roomPhotoGrid}>
          {photos.map((photo) => (
            <View key={photo.key} style={styles.roomPhotoItem}>
              <RoomPhotoImage photo={photo} showError />
              <Pressable onPress={() => removePhoto(photo)} style={({ pressed }) => [styles.removePhotoButton, pressed && styles.pressed]}>
                <Text style={styles.removePhotoText}>Hapus</Text>
              </Pressable>
            </View>
          ))}
        </View>
      ) : <View style={styles.emptyPhoto}><Text style={styles.muted}>Belum ada foto dipilih.</Text></View>}
      {canAddPhoto ? <SecondaryButton title={`Tambah Foto Kamar (${photos.length}/5)`} onPress={onPick} /> : <Text style={styles.muted}>Foto kamar sudah mencapai batas maksimal.</Text>}
      <FooterButtons onClose={onClose} onSave={onSave} loading={loading} saveTitle={isEdit ? 'Simpan Perubahan' : 'Simpan Kamar'} />
    </BaseModal>
  );
}

function OccupantFormModal({ visible, form, setForm, rooms, emptyRooms, apiBase, onPickKtp, onSave, onClose, loading }) {
  const isEdit = Boolean(form.id);
  const ktpUri = form.foto_ktp?.uri || (form.existing_foto_ktp ? storageUrl(form.existing_foto_ktp, apiBase) : null);
  const selectedRoom = rooms.find((room) => Number(room.id) === Number(form.kamar_id));
  const isRoomLocked = !isEdit && Boolean(form.kamar_id);
  const emptyRoomOptions = emptyRooms.map((room) => ({
    value: String(room.id),
    label: roomPickerLabel(room),
  }));
  return (
    <BaseModal visible={visible} title={isEdit ? 'Edit Penghuni' : 'Tambah Penghuni'} onClose={onClose}>
      <Text style={styles.muted}>{isEdit ? 'Perbaiki data penghuni jika ada salah input. Kamar tidak ikut diubah dari form ini.' : 'Pilih kamar kosong, lalu isi data utama penghuni.'}</Text>
      {isEdit ? (
        <View style={styles.lockedInfo}>
          <Text style={styles.lockedTitle}>Kamar {roomName(rooms, form.kamar_id)}</Text>
          <Text style={styles.muted}>Data kamar tetap. Untuk mengosongkan kamar, gunakan tombol Keluarkan Penghuni.</Text>
        </View>
      ) : isRoomLocked ? (
        <View style={styles.lockedInfo}>
          <Text style={styles.lockedTitle}>Kamar {selectedRoom?.nomor_kamar || form.kamar_id}</Text>
          <Text style={styles.muted}>Penghuni akan masuk ke kamar ini. Jika ingin memilih kamar lain, buka dari menu tambah penghuni.</Text>
        </View>
      ) : (
        <>
          <Text style={styles.label}>Kamar kosong</Text>
          <OptionGrid items={emptyRoomOptions} value={form.kamar_id} onChange={(kamar_id) => setForm({ ...form, kamar_id })} emptyText="Belum ada kamar kosong." />
        </>
      )}
      <FormField label="Nama lengkap" value={form.nama_lengkap} onChangeText={(v) => setForm({ ...form, nama_lengkap: v })} />
      <FormField label="Nomor WhatsApp" value={form.no_wa} onChangeText={(v) => setForm({ ...form, no_wa: v })} keyboardType="phone-pad" />
      <FormField label="Nomor KTP" value={form.no_ktp} onChangeText={(v) => setForm({ ...form, no_ktp: v })} />
      <Text style={styles.label}>Upload KTP</Text>
      {ktpUri ? <Image source={{ uri: ktpUri }} style={styles.preview} /> : <View style={styles.emptyPhoto}><Text style={styles.muted}>Belum ada foto KTP dipilih.</Text></View>}
      <SecondaryButton title={form.foto_ktp ? 'Ganti Foto KTP' : 'Pilih Foto KTP'} onPress={onPickKtp} />
      <CompactDatePicker label="Tanggal masuk" value={form.tanggal_masuk} onChange={(tanggal_masuk) => setForm({ ...form, tanggal_masuk })} />
      {!isEdit ? (
        <>
          <Text style={styles.label}>Pembayaran awal</Text>
          <Segment items={['belum_bayar', 'dp', 'lunas']} labels={{ belum_bayar: 'Belum bayar', dp: 'DP', lunas: 'Lunas' }} value={form.pembayaran_awal} onChange={(pembayaran_awal) => setForm({ ...form, pembayaran_awal, nominal_pembayaran_awal: pembayaran_awal === 'dp' ? form.nominal_pembayaran_awal : '' })} />
          {form.pembayaran_awal === 'dp' ? (
            <FormField label="Nominal DP" value={form.nominal_pembayaran_awal} onChangeText={(v) => setForm({ ...form, nominal_pembayaran_awal: formatNumberInput(v) })} keyboardType="number-pad" helperText={form.nominal_pembayaran_awal ? `${money(form.nominal_pembayaran_awal)} akan mengurangi tagihan bulan masuk.` : 'Isi DP yang sudah diterima.'} />
          ) : (
            <Text style={styles.muted}>{form.pembayaran_awal === 'lunas' ? 'Tagihan bulan masuk langsung tercatat lunas.' : 'Tagihan bulan masuk otomatis dibuat sebagai tunggakan.'}</Text>
          )}
        </>
      ) : null}
      <FormField label="Pekerjaan" value={form.pekerjaan} onChangeText={(v) => setForm({ ...form, pekerjaan: v })} />
      <FormField label="Alamat asal" value={form.alamat_asal} onChangeText={(v) => setForm({ ...form, alamat_asal: v })} multiline />
      <FormField label="No kendaraan" value={form.no_kendaraan} onChangeText={(v) => setForm({ ...form, no_kendaraan: v })} />
      <FormField label="Kontak darurat" value={form.kontak_darurat} onChangeText={(v) => setForm({ ...form, kontak_darurat: v })} />
      <FormField label="Catatan pemilik" value={form.catatan_pemilik} onChangeText={(v) => setForm({ ...form, catatan_pemilik: v })} multiline placeholder="Contoh: Janji melunasi sisa pembayaran tanggal 25." helperText="Hanya untuk pengingat pemilik kos dan tidak tampil di portal penghuni." />
      <FooterButtons onClose={onClose} onSave={onSave} loading={loading} saveTitle={isEdit ? 'Simpan Perubahan' : 'Simpan Penghuni'} />
    </BaseModal>
  );
}

function BillFormModal({ visible, form, setForm, rooms, onSave, onClose, loading }) {
  return (
    <BaseModal visible={visible} title="Buat Tagihan Bulanan" onClose={onClose}>
      <Text style={styles.muted}>Tanggal jatuh tempo otomatis mengikuti tanggal jatuh tempo penghuni.</Text>
      <Text style={styles.label}>Kamar</Text>
      <OptionGrid items={[{ value: '', label: 'Semua kamar' }, ...rooms.map((room) => ({ value: String(room.id), label: `Kamar ${room.nomor_kamar}` }))]} value={form.kamar_id} onChange={(kamar_id) => setForm({ ...form, kamar_id })} />
      <MonthYearPicker month={form.bulan} year={form.tahun} onChange={(next) => setForm({ ...form, ...next })} monthLabel="Bulan tagihan" />
      <StepperPicker label="Jumlah bulan" value={form.jumlah_bulan} min={1} max={12} onChange={(jumlah_bulan) => setForm({ ...form, jumlah_bulan })} helperText="Pilih 1 untuk satu bulan, atau tambah jika ingin membuat beberapa bulan sekaligus." />
      <FooterButtons onClose={onClose} onSave={onSave} loading={loading} saveTitle="Buat Tagihan" />
    </BaseModal>
  );
}

function MultiPayModal({ visible, form, setForm, rooms, occupant, onSave, onClose, loading }) {
  const locked = Boolean(occupant);
  return (
    <BaseModal visible={visible} title="Catat Pembayaran" onClose={onClose}>
      <Text style={styles.muted}>{locked ? 'Form ini hanya untuk penghuni yang tadi dipilih. Gunakan saat penghuni membayar tunai atau membayar langsung ke pemilik kos.' : 'Pilih kamar penghuni yang membayar, lalu catat bulan dan jumlah bulan yang dibayar.'}</Text>
      {locked ? (
        <View style={styles.lockedInfo}>
          <Text style={styles.lockedTitle}>{occupant.nama_lengkap}</Text>
          <Text style={styles.muted}>Kamar {roomName(rooms, occupant.kamar_id)} - pembayaran akan dicatat untuk penghuni ini saja.</Text>
        </View>
      ) : (
        <>
          <Text style={styles.label}>Kamar terisi</Text>
          <OptionGrid items={rooms.filter((room) => room.status === 'terisi').map((room) => ({ value: String(room.id), label: `Kamar ${room.nomor_kamar}` }))} value={form.kamar_id} onChange={(kamar_id) => setForm({ ...form, kamar_id, penghuni_id: '' })} emptyText="Belum ada kamar terisi." />
        </>
      )}
      <MonthYearPicker month={form.bulan} year={form.tahun} onChange={(next) => setForm({ ...form, ...next })} monthLabel="Mulai bulan" />
      <StepperPicker label="Jumlah bulan dibayar" value={form.jumlah_bulan} min={1} max={12} onChange={(jumlah_bulan) => setForm({ ...form, jumlah_bulan })} helperText="Contoh: pilih 2 jika penghuni membayar bulan ini dan bulan berikutnya sekaligus." />
      <CompactDatePicker label="Tanggal bayar" value={form.tanggal_bayar} onChange={(tanggal_bayar) => setForm({ ...form, tanggal_bayar })} />
      {locked ? (
        <View style={styles.lockedInfo}>
          <Text style={styles.lockedTitle}>Bayar tunai</Text>
          <Text style={styles.muted}>Metode ini sudah tetap karena pemilik mencatat pembayaran langsung dari penghuni.</Text>
        </View>
      ) : (
        <>
          <Text style={styles.label}>Metode pembayaran</Text>
          <OptionGrid items={paymentOptionRows} value={form.metode_pembayaran} onChange={(metode_pembayaran) => setForm({ ...form, metode_pembayaran })} />
        </>
      )}
      <FooterButtons onClose={onClose} onSave={onSave} loading={loading} saveTitle="Catat Bayar" />
    </BaseModal>
  );
}

function RoomStatusModal({ visible, form, setForm, onSave, onClose, loading }) {
  return (
    <BaseModal visible={visible} title={`Status Kamar ${form.nomor_kamar || ''}`} onClose={onClose}>
      <Text style={styles.muted}>Pilih kosong jika kamar siap ditempati, atau maintenance jika kamar sedang diperbaiki dan belum dapat diisi.</Text>
      <Text style={styles.label}>Status kamar</Text>
      <Segment items={['kosong', 'maintenance']} labels={{ kosong: 'Kosong', maintenance: 'Maintenance' }} value={form.status} onChange={(status) => setForm({ ...form, status })} />
      <FooterButtons onClose={onClose} onSave={onSave} loading={loading} />
    </BaseModal>
  );
}

function PeriodPickerModal({ visible, draft, setDraft, onSave, onClose }) {
  const year = Number(draft.tahun || thisYear());
  return (
    <BaseModal visible={visible} title="Pilih Periode" onClose={onClose}>
      <View style={styles.yearStepper}>
        <SecondaryButton title="<" onPress={() => setDraft({ ...draft, tahun: String(year - 1) })} style={styles.yearButton} />
        <Text style={styles.yearText}>{year}</Text>
        <SecondaryButton title=">" onPress={() => setDraft({ ...draft, tahun: String(year + 1) })} style={styles.yearButton} />
      </View>
      <Text style={styles.label}>Bulan</Text>
      <OptionGrid items={monthOptions} value={String(draft.bulan)} onChange={(bulan) => setDraft({ ...draft, bulan })} />
      <FooterButtons onClose={onClose} onSave={onSave} saveTitle="Terapkan" />
    </BaseModal>
  );
}

function PaymentFormModal({ visible, form, setForm, onSave, onClose, loading }) {
  const isQris = form.jenis === 'qris';
  return (
    <BaseModal visible={visible} title="Metode Pembayaran" onClose={onClose}>
      <Text style={styles.label}>Alur pembayaran</Text>
      <Segment items={['qris', 'bank']} labels={{ qris: 'QRIS Otomatis (Rekomendasi)', bank: 'Transfer Bank' }} value={form.jenis} onChange={(jenis) => setForm({ ...form, jenis, nama_bank: jenis === 'qris' ? '' : form.nama_bank, nomor_rekening: jenis === 'qris' ? '' : form.nomor_rekening, atas_nama: jenis === 'qris' ? '' : form.atas_nama })} />
      <View style={styles.notice}>
        <Text style={styles.noticeText}>{isQris ? 'Penghuni membayar dengan scan QRIS. Total bayar ditambah biaya layanan 0,9%, lalu nominal sewa masuk ke saldo kos.' : 'Penghuni melihat rekening ini di portal, mengunggah bukti transfer, lalu pemilik kos melakukan verifikasi manual.'}</Text>
      </View>
      {isQris ? (
        <>
          <FormField label="Kode akun pembayaran" value={form.gateway_account_id} onChangeText={(v) => setForm({ ...form, gateway_account_id: v })} placeholder="Opsional" />
          <FormField label="Catatan instruksi" value={form.instruksi_pembayaran} onChangeText={(v) => setForm({ ...form, instruksi_pembayaran: v })} multiline placeholder="Scan QRIS. Total bayar termasuk biaya layanan 0,9%." />
        </>
      ) : (
        <>
          <FormField label="Nama bank" value={form.nama_bank} onChangeText={(v) => setForm({ ...form, nama_bank: v })} placeholder="BCA, BRI, Mandiri" />
          <FormField label="Nomor rekening" value={form.nomor_rekening} onChangeText={(v) => setForm({ ...form, nomor_rekening: v })} keyboardType="number-pad" />
          <FormField label="Atas nama" value={form.atas_nama} onChangeText={(v) => setForm({ ...form, atas_nama: v })} />
          <FormField label="Instruksi pembayaran" value={form.instruksi_pembayaran} onChangeText={(v) => setForm({ ...form, instruksi_pembayaran: v })} multiline placeholder="Transfer sesuai nominal tagihan, lalu unggah bukti pembayaran." />
        </>
      )}
      <ToggleRow label="Aktif" value={form.is_active} onValueChange={(value) => setForm({ ...form, is_active: value })} />
      <FooterButtons onClose={onClose} onSave={onSave} loading={loading} />
    </BaseModal>
  );
}

function WithdrawModal({ visible, form, setForm, wallet, onSave, onClose, loading }) {
  return (
    <BaseModal visible={visible} title="Tarik Saldo QRIS" onClose={onClose}>
      <Text style={styles.muted}>Saldo yang bisa ditarik adalah saldo QRIS yang sudah lewat masa tunggu H+3. Pengajuan akan masuk status menunggu proses.</Text>
      <View style={styles.lockedInfo}>
        <Text style={styles.lockedTitle}>Saldo bisa ditarik H+3</Text>
        <Text style={styles.money}>{money(wallet?.saldo_tersedia)}</Text>
        <Text style={styles.muted}>Nominal ini mungkin berbeda dari total saldo Dompet QRIS karena pembayaran baru belum bisa ditarik.</Text>
      </View>
      <FormField label="Nominal tarik" value={form.nominal} onChangeText={(v) => setForm({ ...form, nominal: formatNumberInput(v) })} keyboardType="number-pad" helperText={form.nominal ? money(form.nominal) : 'Minimal Rp 10.000'} />
      <FormField label="Nama bank" value={form.nama_bank} onChangeText={(v) => setForm({ ...form, nama_bank: v })} placeholder="BCA, BRI, Mandiri" />
      <FormField label="Nomor rekening" value={form.nomor_rekening} onChangeText={(v) => setForm({ ...form, nomor_rekening: v })} keyboardType="number-pad" helperText="Periksa ulang nomor rekening sebelum mengajukan." />
      <FormField label="Atas nama" value={form.atas_nama} onChangeText={(v) => setForm({ ...form, atas_nama: v })} helperText="Isi persis seperti nama di rekening bank." />
      <FooterButtons onClose={onClose} onSave={onSave} loading={loading} saveTitle="Ajukan Tarik Saldo" />
    </BaseModal>
  );
}

function InitialPaymentCorrectionModal({ visible, form, setForm, onSave, onClose, loading }) {
  return (
    <BaseModal visible={visible} title="Koreksi DP" onClose={onClose}>
      <Text style={styles.muted}>Gunakan jika nominal DP saat penghuni masuk salah. Perubahan ini akan langsung menyesuaikan sisa tagihan dan laporan pemasukan sewa.</Text>
      <View style={styles.lockedInfo}>
        <Text style={styles.lockedTitle}>Harga sewa kamar</Text>
        <Text style={styles.money}>{money(form.harga_kamar)}</Text>
      </View>
      <FormField label="Nominal DP diterima" value={form.nominal} onChangeText={(v) => setForm({ ...form, nominal: formatNumberInput(v) })} keyboardType="number-pad" helperText="Isi 0 jika DP sebelumnya ternyata belum diterima." />
      <CompactDatePicker label="Tanggal DP diterima" value={form.tanggal_bayar} onChange={(tanggal_bayar) => setForm({ ...form, tanggal_bayar })} />
      <FooterButtons onClose={onClose} onSave={onSave} loading={loading} saveTitle="Simpan Koreksi DP" />
    </BaseModal>
  );
}

function PaymentFlowSummary({ method }) {
  if (!method) return <Empty text="Belum ada alur pembayaran aktif." />;
  const isAuto = method.jenis === 'qris' || method.verification_mode === 'automatic';
  return (
    <View style={[styles.card, isAuto && styles.paymentAutoCard]}>
      <Text style={styles.cardTitle}>{isAuto ? 'QRIS otomatis aktif' : 'Transfer bank manual aktif'}</Text>
      <Text style={styles.muted}>{isAuto ? 'Nominal sewa masuk ke saldo kos dan bisa ditarik.' : 'Rekening bank akan tampil di portal penghuni. Bukti bayar tetap perlu diverifikasi pemilik kos.'}</Text>
    </View>
  );
}

function QrisWalletCard({ wallet, onWithdraw }) {
  if (!wallet) return null;

  return (
    <View style={[styles.card, styles.paymentAutoCard]}>
      <Text style={styles.cardTitle}>Dompet QRIS</Text>
      <Text style={styles.muted}>Nominal sewa masuk ke dompet QRIS dan bisa dipantau di sini.</Text>
      <View style={styles.summaryGrid}>
        <View style={styles.summaryItem}><Text style={styles.muted}>Saldo dompet</Text><Text style={styles.summaryValue}>{money(Number(wallet?.saldo_tersedia || 0) + Number(wallet?.saldo_pending || 0))}</Text></View>
        <View style={styles.summaryItem}><Text style={styles.muted}>Total ditarik</Text><Text style={styles.summaryValue}>{money(wallet?.total_ditarik)}</Text></View>
      </View>
      <SecondaryButton title="Tarik Saldo" onPress={onWithdraw} style={styles.cardButton} />
    </View>
  );
}

function PaymentMethodCard({ item, onToggle, onDelete }) {
  const active = Number(item.is_active) === 1 || item.is_active === true;
  return (
    <View style={styles.card}>
      <View style={styles.rowBetween}>
        <Text style={styles.cardTitle}>{paymentMethodTitle(item)}</Text>
        <Text style={[styles.badge, active ? styles.activeText : styles.inactiveText]}>{active ? 'Aktif' : 'Nonaktif'}</Text>
      </View>
      {paymentMethodLines(item).slice(1).map((line, index) => <Text key={`${item.id}-${index}`} style={styles.muted}>{line}</Text>)}
      <View style={styles.actionRow}>
        <SecondaryButton title={active ? 'Nonaktifkan' : 'Aktifkan'} onPress={onToggle} style={styles.flexButton} />
        <SecondaryButton title="Hapus" onPress={onDelete} style={[styles.flexButton, styles.dangerButton]} />
      </View>
    </View>
  );
}

function PaymentInfoModal({ visible, onClose }) {
  return (
    <BaseModal visible={visible} title="Info Alur Pembayaran" onClose={onClose}>
      <View style={styles.infoBlock}>
        <Text style={styles.infoTitle}>1. Pilih cara menerima pembayaran</Text>
        <Text style={styles.infoText}>Pemilik kos cukup memilih salah satu metode aktif untuk kos ini. Metode aktif itulah yang nanti dilihat penghuni di portal pembayaran.</Text>
      </View>
      <View style={styles.infoBlock}>
        <Text style={styles.infoTitle}>QRIS Otomatis</Text>
        <Text style={styles.infoText}>Pilih ini kalau ingin pembayaran lebih praktis. Penghuni scan QRIS, membayar sesuai tagihan, lalu sistem menandai tagihan lunas otomatis setelah pembayaran berhasil. Uang masuk ke saldo kos dan nantinya bisa ditarik oleh pemilik.</Text>
      </View>
      <View style={styles.infoBlock}>
        <Text style={styles.infoTitle}>Transfer Bank Manual</Text>
        <Text style={styles.infoText}>Pilih ini kalau pemilik ingin memakai rekening sendiri. Nomor rekening akan tampil di portal penghuni. Setelah transfer, penghuni upload bukti bayar. Pemilik harus mengecek bukti tersebut lalu menekan Verifikasi jika sudah benar.</Text>
      </View>
      <View style={styles.notice}>
        <Text style={styles.noticeText}>Sederhananya: QRIS otomatis cocok kalau pemilik tidak ingin mengecek bukti pembayaran satu per satu. Transfer bank cocok kalau pemilik masih ingin memeriksa bukti secara manual.</Text>
      </View>
      <PrimaryButton title="Mengerti" onPress={onClose} />
    </BaseModal>
  );
}

function FinanceFormModal({ visible, form, setForm, onSave, onClose, loading }) {
  return (
    <BaseModal visible={visible} title={form.id ? 'Edit Transaksi' : 'Tambah Transaksi'} onClose={onClose}>
      <Text style={styles.label}>Jenis</Text>
      <Segment items={['pemasukan', 'pengeluaran']} value={form.jenis} onChange={(jenis) => setForm({ ...form, jenis })} />
      <CompactDatePicker label="Tanggal transaksi" value={form.tanggal} onChange={(tanggal) => setForm({ ...form, tanggal })} />
      <FormField label="Nominal" value={form.nominal} onChangeText={(v) => setForm({ ...form, nominal: formatNumberInput(v) })} keyboardType="number-pad" helperText={form.nominal ? money(form.nominal) : 'Isi angka saja.'} />
      <FormField label="Keterangan" value={form.keterangan} onChangeText={(v) => setForm({ ...form, keterangan: v })} multiline />
      <FooterButtons onClose={onClose} onSave={onSave} loading={loading} saveTitle={form.id ? 'Simpan Perubahan' : 'Simpan'} />
    </BaseModal>
  );
}

function MonthYearPicker({ month, year, onChange, monthLabel = 'Bulan' }) {
  const currentYear = Number(year || thisYear());
  return (
    <View style={styles.pickerGroup}>
      <Text style={styles.label}>{monthLabel}</Text>
      <OptionGrid items={monthOptions} value={String(month)} onChange={(bulan) => onChange({ bulan })} />
      <Text style={styles.label}>Tahun</Text>
      <View style={styles.yearStepper}>
        <SecondaryButton title="<" onPress={() => onChange({ tahun: String(currentYear - 1) })} style={styles.yearButton} />
        <Text style={styles.yearText}>{currentYear}</Text>
        <SecondaryButton title=">" onPress={() => onChange({ tahun: String(currentYear + 1) })} style={styles.yearButton} />
      </View>
    </View>
  );
}

function StepperPicker({ label, value, min = 1, max = 12, onChange, helperText }) {
  const number = Math.min(max, Math.max(min, Number(value || min)));
  return (
    <View style={styles.pickerGroup}>
      <Text style={styles.label}>{label}</Text>
      <View style={styles.stepperRow}>
        <SecondaryButton title="-" onPress={() => onChange(String(Math.max(min, number - 1)))} style={styles.stepperButton} />
        <Text style={styles.stepperValue}>{number}</Text>
        <SecondaryButton title="+" onPress={() => onChange(String(Math.min(max, number + 1)))} style={styles.stepperButton} />
      </View>
      {helperText ? <Text style={styles.muted}>{helperText}</Text> : null}
    </View>
  );
}

function CompactDatePicker({ label, value, onChange }) {
  const [open, setOpen] = useState(false);
  const parts = dateParts(value);
  const firstDay = new Date(parts.year, parts.month - 1, 1).getDay();
  const totalDays = daysInMonth(parts.year, parts.month);
  const cells = [
    ...Array.from({ length: firstDay }, () => null),
    ...Array.from({ length: totalDays }, (_, index) => index + 1),
  ];

  function setPart(next) {
    const year = next.year ?? parts.year;
    const month = next.month ?? parts.month;
    const maxDay = daysInMonth(year, month);
    const day = Math.min(next.day ?? parts.day, maxDay);
    onChange(`${year}-${pad2(month)}-${pad2(day)}`);
  }

  return (
    <View style={styles.compactDate}>
      <Text style={styles.label}>{label}</Text>
      <Pressable onPress={() => setOpen((current) => !current)} style={({ pressed }) => [styles.dateField, pressed && styles.pressed]}>
        <Text style={styles.dateFieldText}>{parts.day} {monthName(parts.month)} {parts.year}</Text>
        <Text style={styles.dateFieldHint}>{open ? 'Tutup' : 'Pilih'}</Text>
      </Pressable>
      {open ? (
        <View style={styles.calendarPanel}>
          <View style={styles.calendarHeader}>
            <SecondaryButton title="<" onPress={() => setPart(parts.month === 1 ? { month: 12, year: parts.year - 1 } : { month: parts.month - 1 })} style={styles.calendarNav} />
            <Text style={styles.calendarTitle}>{monthName(parts.month)} {parts.year}</Text>
            <SecondaryButton title=">" onPress={() => setPart(parts.month === 12 ? { month: 1, year: parts.year + 1 } : { month: parts.month + 1 })} style={styles.calendarNav} />
          </View>
          <View style={styles.weekRow}>{['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'].map((day) => <Text key={day} style={styles.weekText}>{day}</Text>)}</View>
          <View style={styles.dayGrid}>
            {cells.map((day, index) => (
              <Pressable
                key={`${day || 'blank'}-${index}`}
                disabled={!day}
                onPress={() => {
                  setPart({ day });
                  setOpen(false);
                }}
                style={[styles.dayCell, day === parts.day && styles.dayCellActive, !day && styles.dayCellBlank]}
              >
                <Text style={[styles.dayText, day === parts.day && styles.dayTextActive]}>{day || ''}</Text>
              </Pressable>
            ))}
          </View>
        </View>
      ) : null}
    </View>
  );
}

function AnnouncementFormModal({ visible, form, setForm, onSave, onClose, loading }) {
  return (
    <BaseModal visible={visible} title={form.id ? 'Edit Pengumuman' : 'Tambah Pengumuman'} onClose={onClose}>
      <FormField label="Judul" value={form.judul} onChangeText={(v) => setForm({ ...form, judul: v })} />
      <FormField label="Isi pengumuman" value={form.isi} onChangeText={(v) => setForm({ ...form, isi: v })} multiline />
      <Text style={styles.label}>Status</Text>
      <Segment items={['aktif', 'nonaktif']} value={form.status} onChange={(status) => setForm({ ...form, status })} />
      <FooterButtons onClose={onClose} onSave={onSave} loading={loading} saveTitle={form.id ? 'Simpan Perubahan' : 'Simpan'} />
    </BaseModal>
  );
}

const facilityRows = [
  ['fasilitas_ac', 'AC'],
  ['fasilitas_km_dalam', 'Kamar mandi dalam'],
  ['fasilitas_dapur_dalam', 'Dapur dalam'],
  ['fasilitas_wifi', 'WiFi'],
  ['fasilitas_kasur', 'Kasur'],
  ['fasilitas_lemari', 'Lemari'],
  ['fasilitas_meja', 'Meja'],
  ['fasilitas_parkir', 'Parkir'],
];

function isTruthy(value) {
  return Number(value) === 1 || value === true;
}

function facilityLabels(room) {
  return facilityRows.filter(([key]) => isTruthy(room?.[key])).map(([, label]) => label);
}

function roomToForm(room) {
  return {
    ...emptyRoomForm,
    id: room.id,
    has_active_occupant: Boolean(room.penghuni_aktif),
    nomor_kamar: String(room.nomor_kamar || ''),
    tipe_kamar: String(room.tipe_kamar || ''),
    harga_bulanan: formatNumberInput(room.harga_bulanan),
    status: room.penghuni_aktif ? 'terisi' : (room.status === 'terisi' ? 'kosong' : (room.status || 'kosong')),
    fasilitas_ac: isTruthy(room.fasilitas_ac),
    fasilitas_km_dalam: isTruthy(room.fasilitas_km_dalam),
    fasilitas_dapur_dalam: isTruthy(room.fasilitas_dapur_dalam),
    fasilitas_wifi: isTruthy(room.fasilitas_wifi),
    fasilitas_kasur: isTruthy(room.fasilitas_kasur),
    fasilitas_lemari: isTruthy(room.fasilitas_lemari),
    fasilitas_meja: isTruthy(room.fasilitas_meja),
    fasilitas_parkir: isTruthy(room.fasilitas_parkir),
    catatan: room.catatan || '',
    fotos: [],
    existing_fotos: (room.fotos || (room.foto ? [{ id: null, path: room.foto, url: room.foto_url }] : [])).map((photo) => ({
      id: photo.id,
      path: photo.path,
      url: photo.url,
    })),
    hapus_foto_ids: [],
  };
}
const paymentOptionRows = [
  { value: 'tunai', label: 'Tunai' },
  { value: 'transfer', label: 'Transfer' },
  { value: 'qris', label: 'QRIS' },
];

function paymentMethodLabel(value) {
  return paymentOptionRows.find((item) => item.value === value)?.label || value || '-';
}

function Shell({ children }) {
  return (
    <KeyboardAvoidingView style={styles.app} behavior={Platform.OS === 'ios' ? 'padding' : 'height'}>
      <ScrollView
        keyboardShouldPersistTaps="handled"
        keyboardDismissMode="interactive"
        contentContainerStyle={styles.content}
      >
        {children}
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

function BaseModal({ visible, title, children, onClose }) {
  return (
    <Modal visible={visible} animationType="slide" onRequestClose={onClose || (() => {})}>
      <KeyboardAvoidingView style={styles.app} behavior={Platform.OS === 'ios' ? 'padding' : 'height'}>
        <ScrollView
          keyboardShouldPersistTaps="handled"
          keyboardDismissMode="interactive"
          contentContainerStyle={styles.modalContent}
          scrollIndicatorInsets={{ bottom: 120 }}
        >
          <Text style={styles.sectionTitle}>{title}</Text>
          {children}
        </ScrollView>
      </KeyboardAvoidingView>
    </Modal>
  );
}

function FooterButtons({ onClose, onSave, loading, saveTitle = 'Simpan' }) {
  return (
    <View style={styles.footer}>
      <SecondaryButton title="Batal" onPress={onClose} style={{ flex: 1 }} />
      <PrimaryButton title={saveTitle} onPress={onSave} loading={loading} style={{ flex: 1.3 }} />
    </View>
  );
}

function RoomPhotoImage({ photo, showError = false, onFail, style }) {
  const sources = photo.sources?.length ? photo.sources : [photo.uri].filter(Boolean);
  const [sourceIndex, setSourceIndex] = useState(0);
  const [displayUri, setDisplayUri] = useState(null);
  const [loadingPhoto, setLoadingPhoto] = useState(false);
  const uri = sources[sourceIndex];
  const sourceSignature = sources.join('|');

  useEffect(() => {
    setSourceIndex(0);
  }, [sourceSignature]);

  useEffect(() => {
    let alive = true;
    setDisplayUri(null);
    setLoadingPhoto(false);

    if (!uri) return undefined;
    if (!/^https?:\/\//.test(uri)) {
      setDisplayUri(uri);
      return undefined;
    }

    setLoadingPhoto(true);
    cachedRoomPhotoUri(uri)
      .then((cachedUri) => {
        if (alive) setDisplayUri(cachedUri);
      })
      .catch((error) => {
        console.warn('Room photo download failed', uri, error?.message || error);
        if (alive) {
          if (sourceIndex >= sources.length - 1) onFail?.();
          setSourceIndex((current) => current + 1);
        }
      })
      .finally(() => {
        if (alive) setLoadingPhoto(false);
      });

    return () => {
      alive = false;
    };
  }, [uri]);

  if (!uri || sourceIndex >= sources.length) {
    return showError ? <View style={[styles.roomPhotoThumb, style, styles.photoError]}><Text style={styles.photoErrorText}>Foto gagal dimuat</Text></View> : null;
  }

  if (loadingPhoto || !displayUri) {
    return <View style={[styles.roomPhotoThumb, style, styles.photoError]}><Text style={styles.photoErrorText}>Memuat foto...</Text></View>;
  }

  return (
    <Image
      source={{ uri: displayUri }}
      style={[styles.roomPhotoThumb, style]}
      resizeMode="cover"
      fadeDuration={0}
      onError={(event) => {
        console.warn('Room photo failed', uri, event?.nativeEvent?.error);
        if (sourceIndex >= sources.length - 1) onFail?.();
        setSourceIndex(sourceIndex + 1);
      }}
    />
  );
}

function Dashboard({ data, occupants = [], activeKos, setTab, setOccupantFilter }) {
  if (!data) return <Empty text="Memuat dashboard..." />;
  const unpaidAmount = Number(data.tagihan_belum_lunas || 0);
  const waitingCount = Number(data.tagihan_menunggu_verifikasi || 0);
  const emptyRoomCount = Number(data.kamar_kosong || 0);
  const maintenanceCount = Number(data.kamar_maintenance || 0);
  const totalRooms = Number(data.total_kamar || 0);
  const occupiedRooms = Number(data.kamar_terisi || 0);
  const occupancy = Number(data.okupansi || 0);
  const verifyOccupantCount = occupants.filter((item) => Number(item.tagihan_verifikasi_count || 0) > 0).length;
  const unpaidOccupantCount = occupants.filter((item) => Number(item.tagihan_aktif_count || 0) > 0).length;
  const dueSoonCount = occupants.filter((item) => item.akan_jatuh_tempo).length;
  const openOccupants = (filter = 'semua') => {
    setOccupantFilter?.(filter);
    setTab('penghuni');
  };
  const healthTitle = waitingCount > 0
    ? 'Ada pembayaran perlu dicek'
    : unpaidAmount > 0
      ? 'Masih ada tagihan belum lunas'
      : dueSoonCount > 0
        ? 'Tagihan jatuh tempo'
        : emptyRoomCount > 0
          ? 'Ada kamar kosong'
          : 'Kos sedang aman';
  const healthText = waitingCount > 0
    ? `${waitingCount} bukti bayar menunggu verifikasi. Cek dulu agar penghuni tidak menunggu.`
    : unpaidAmount > 0
      ? `${money(unpaidAmount)} belum lunas. Bagikan portal atau ingatkan penghuni.`
      : dueSoonCount > 0
        ? `${dueSoonCount} penghuni akan jatuh tempo. Buat tagihan agar siap dibagikan ke penghuni.`
        : emptyRoomCount > 0
          ? `${emptyRoomCount} kamar kosong bisa segera diisi penghuni baru.`
          : 'Tidak ada pekerjaan mendesak dari data saat ini.';
  const tasks = [
    waitingCount > 0 && { title: 'Verifikasi bukti bayar', text: `${verifyOccupantCount || waitingCount} pembayaran menunggu dicek`, onPress: () => openOccupants('verifikasi'), primary: true },
    unpaidAmount > 0 && { title: 'Tagihan belum lunas', text: unpaidOccupantCount > 0 ? `${unpaidOccupantCount} penghuni belum lunas atau terlambat` : money(unpaidAmount), onPress: () => openOccupants('tagihan') },
    dueSoonCount > 0 && { title: 'Tagihan jatuh tempo', text: `${dueSoonCount} penghuni akan jatuh tempo`, onPress: () => openOccupants('jatuh_tempo') },
    emptyRoomCount > 0 && { title: 'Isi kamar kosong', text: `${emptyRoomCount} kamar siap ditawarkan`, onPress: () => setTab('kamar') },
    maintenanceCount > 0 && { title: 'Cek kamar maintenance', text: `${maintenanceCount} kamar belum bisa ditempati`, onPress: () => setTab('kamar') },
  ].filter(Boolean);

  return (
    <>
      <Text style={styles.scopeText}>Menampilkan data: {activeKos?.nama_kos || 'kos dipilih'}</Text>
      <View style={styles.dashboardHero}>
        <Text style={styles.dashboardEyebrow}>Status hari ini</Text>
        <Text style={styles.dashboardTitle}>{healthTitle}</Text>
        <Text style={styles.dashboardText}>{healthText}</Text>
        <View style={styles.occupancyTrack}>
          <View style={[styles.occupancyFill, { width: `${Math.min(100, Math.max(0, occupancy))}%` }]} />
        </View>
        <View style={styles.dashboardMetaRow}>
          <Text style={styles.dashboardMeta}>{occupiedRooms}/{totalRooms} kamar terisi</Text>
          <Text style={styles.dashboardMeta}>{occupancy}% okupansi</Text>
        </View>
      </View>

      <Text style={styles.sectionTitle}>Perlu dilakukan</Text>
      {tasks.length === 0 ? (
        <View style={styles.softCard}>
          <Text style={styles.cardTitle}>Belum ada tugas mendesak</Text>
          <Text style={styles.muted}>Pantau kamar, penghuni, dan pembayaran dari tombol cepat di bawah.</Text>
        </View>
      ) : tasks.map((task) => (
        <Pressable key={task.title} onPress={task.onPress} style={({ pressed }) => [styles.taskCard, task.primary && styles.taskCardPrimary, pressed && styles.pressed]}>
          <View style={styles.taskIcon}><Text style={styles.taskIconText}>{task.primary ? '!' : '>'}</Text></View>
          <View style={styles.taskBody}>
            <Text style={styles.taskTitle}>{task.title}</Text>
            <Text style={styles.taskText}>{task.text}</Text>
          </View>
          <Text style={styles.taskArrow}>Buka</Text>
        </Pressable>
      ))}

      <Text style={styles.sectionTitle}>Ringkasan kos</Text>
      <View style={styles.dashboardGrid}>
        <DashboardMetric label="Kamar kosong" value={emptyRoomCount} hint="Siap ditempati" onPress={() => setTab('kamar')} />
        <DashboardMetric label="Penghuni aktif" value={data.penghuni_aktif || 0} hint="Orang tinggal" onPress={() => openOccupants('semua')} />
        <DashboardMetric label="Belum lunas" value={money(unpaidAmount)} hint="Perlu ditagih" onPress={() => openOccupants('tagihan')} wide />
      </View>

      <Text style={styles.sectionTitle}>Tombol cepat</Text>
      <View style={styles.quickGrid}>
        <QuickAction title="Kelola Kamar" onPress={() => setTab('kamar')} />
        <QuickAction title="Cari Penghuni" onPress={() => openOccupants('semua')} />
        <QuickAction title="Cek Pembayaran" onPress={() => openOccupants('verifikasi')} />
      </View>
    </>
  );
}

function DashboardMetric({ label, value, hint, onPress, wide = false }) {
  return (
    <Pressable onPress={onPress} style={({ pressed }) => [styles.metricCard, wide && styles.metricCardWide, pressed && styles.pressed]}>
      <Text style={styles.metricLabel}>{label}</Text>
      <Text style={styles.metricValue}>{value}</Text>
      <Text style={styles.metricHint}>{hint}</Text>
    </Pressable>
  );
}

function QuickAction({ title, onPress }) {
  return (
    <Pressable onPress={onPress} style={({ pressed }) => [styles.quickAction, pressed && styles.pressed]}>
      <Text style={styles.quickActionText}>{title}</Text>
    </Pressable>
  );
}

function KosPicker({ kosList, activeKosId, setActiveKosId, onAdd, onEdit }) {
  return (
    <View style={styles.kosPills}>
      {kosList.map((kos) => <Pressable key={kos.id} onPress={() => setActiveKosId(kos.id)} style={[styles.kosPill, activeKosId === kos.id && styles.kosPillActive]}><Text style={[styles.kosPillText, activeKosId === kos.id && styles.kosPillTextActive]}>{kos.nama_kos}</Text></Pressable>)}
      <Pressable onPress={onAdd} style={[styles.kosPill, styles.kosAddPill]}><Text style={styles.kosAddText}>+ Kos</Text></Pressable>
      {activeKosId ? <Pressable onPress={onEdit} style={[styles.kosPill, styles.kosEditPill]}><Text style={styles.kosEditText}>Edit Kos</Text></Pressable> : null}
    </View>
  );
}

function BottomNav({ tab, setTab, bottomInset = 0 }) {
  const items = [
    ['dashboard', 'Dashboard', 'grid'],
    ['kamar', 'Kamar', 'bed'],
    ['penghuni', 'Penghuni', 'people'],
    ['lainnya', 'Lainnya', 'settings'],
  ];
  return (
    <View pointerEvents="box-none" style={[styles.bottomNavWrap, { paddingBottom: Math.max(bottomInset, spacing.sm) }]}>
      <View style={styles.bottomNav}>
        {items.map(([key, label, icon]) => {
          const active = tab === key;
          return (
          <Pressable key={key} hitSlop={10} onPress={() => setTab(key)} style={({ pressed }) => [styles.navItem, pressed && styles.pressed]}>
            <Ionicons name={active ? icon : `${icon}-outline`} size={20} color={active ? colors.goldLight : colors.muted} />
            <Text style={[styles.navText, active && styles.navTextActive]}>{label}</Text>
          </Pressable>
          );
        })}
      </View>
    </View>
  );
}

function HeaderAction({ title, action, onPress }) {
  return <View style={styles.rowBetween}><Text style={styles.sectionTitle}>{title}</Text><SecondaryButton title={action} onPress={onPress} style={styles.smallButton} /></View>;
}

function InfoButton({ onPress }) {
  return (
    <Pressable onPress={onPress} style={({ pressed }) => [styles.infoButton, pressed && styles.pressed]}>
      <Text style={styles.infoButtonText}>i</Text>
    </Pressable>
  );
}

function FinanceCard({ item, onEdit, onDelete }) {
  const typeLabel = item.jenis === 'pengeluaran' ? 'Pengeluaran' : 'Pemasukan';
  return (
    <View style={styles.card}>
      <View style={styles.rowBetween}>
        <Text style={styles.cardTitle}>{typeLabel}</Text>
        <Text style={[styles.badge, item.jenis === 'pengeluaran' ? styles.lossValue : styles.activeText]}>{money(item.nominal)}</Text>
      </View>
      <Text style={styles.muted}>{formatDate(item.tanggal)}</Text>
      <Text style={styles.muted}>{item.keterangan || 'Tanpa keterangan'}</Text>
      <View style={styles.actionRow}>
        <SecondaryButton title="Edit" onPress={onEdit} style={styles.flexButton} />
        <SecondaryButton title="Hapus" onPress={onDelete} style={[styles.flexButton, styles.dangerButton]} />
      </View>
    </View>
  );
}

function AnnouncementCard({ item, onToggle, onEdit, onDelete }) {
  const active = item.status === 'aktif';
  return (
    <View style={styles.card}>
      <View style={styles.rowBetween}>
        <Text style={styles.cardTitle}>{item.judul}</Text>
        <Text style={[styles.badge, active ? styles.activeText : styles.inactiveText]}>{active ? 'Aktif' : 'Nonaktif'}</Text>
      </View>
      <Text style={styles.muted}>{item.isi}</Text>
      <SecondaryButton title={active ? 'Nonaktifkan' : 'Aktifkan kembali'} onPress={onToggle} style={styles.cardButton} />
      <View style={styles.actionRow}>
        <SecondaryButton title="Edit" onPress={onEdit} style={styles.flexButton} />
        <SecondaryButton title="Hapus" onPress={onDelete} style={[styles.flexButton, styles.dangerButton]} />
      </View>
    </View>
  );
}

function RoomCard({ room, apiBase, onPress }) {
  const labels = facilityLabels(room);
  const photo = roomPhotos(room, apiBase)[0];
  return (
    <Pressable onPress={onPress} style={({ pressed }) => [styles.card, pressed && styles.pressed]}>
      <View style={styles.rowBetween}><Text style={styles.cardTitle}>Kamar {room.nomor_kamar}</Text><Text style={[styles.badge, statusStyle(room.status)]}>{room.status}</Text></View>
      <Text style={styles.muted}>{room.tipe_kamar || '-'} - {money(room.harga_bulanan)}</Text>
      {photo ? <RoomCardPhoto photo={photo} /> : null}
      {labels.length ? <View style={styles.facilityChipRow}>{labels.slice(0, 4).map((label) => <Text key={label} style={styles.facilityChipSmall}>{label}</Text>)}</View> : <Text style={styles.facilities}>Belum ada fasilitas dipilih</Text>}
      <Text style={styles.linkText}>Lihat detail kamar</Text>
    </Pressable>
  );
}

function RoomCardPhoto({ photo }) {
  const sources = photo.sources?.length ? photo.sources : [photo.uri].filter(Boolean);
  const [sourceIndex, setSourceIndex] = useState(0);
  const sourceSignature = sources.join('|');
  const uri = sources[sourceIndex];

  useEffect(() => {
    setSourceIndex(0);
  }, [sourceSignature]);

  if (!uri || sourceIndex >= sources.length) {
    return null;
  }

  return (
    <Image
      source={{ uri }}
      style={styles.roomCardImage}
      resizeMode="cover"
      fadeDuration={150}
      onError={() => setSourceIndex((current) => current + 1)}
    />
  );
}

function OccupantCard({ item, bills, rooms, onPress }) {
  const openBills = Number(item.tagihan_aktif_count || 0);
  const verifyBills = Number(item.tagihan_verifikasi_count || 0);
  const isExited = item.status === 'keluar';
  const hasDebtAfterExit = isExited && (openBills > 0 || verifyBills > 0);
  const recordedLoss = (bills || []).reduce((total, bill) => total + Number(bill.kerugian_tunggakan || 0), 0);
  const latestBill = [...(bills || [])].sort((a, b) => billSortPriority(a.status) - billSortPriority(b.status))[0];
  const paymentLabel = verifyBills > 0
    ? `${verifyBills} pembayaran perlu dicek`
    : openBills > 0
      ? `${openBills} tagihan belum bayar`
      : item.akan_jatuh_tempo
        ? `Jatuh tempo ${item.jatuh_tempo_berikutnya}`
        : isExited
          ? 'Sudah keluar dan tidak ada tunggakan'
          : 'Pembayaran aman';
  return (
    <Pressable onPress={onPress} style={({ pressed }) => [styles.card, pressed && styles.pressed]}>
      <View style={styles.rowBetween}>
        <Text style={styles.cardTitle}>{item.nama_lengkap}</Text>
        <Text style={[styles.badge, statusStyle(item.status)]}>{item.status}</Text>
      </View>
      {hasDebtAfterExit ? (
        <View style={styles.noticeMini}>
          <Text style={styles.noticeMiniText}>{recordedLoss > 0 ? `Sudah keluar. Tunggakan ${money(recordedLoss)} sudah tercatat sebagai kerugian laporan.` : 'Sudah keluar, tapi masih ada pembayaran yang harus diselesaikan.'}</Text>
        </View>
      ) : null}
      <Text style={styles.muted}>Kamar terakhir {roomName(rooms, item.kamar_id)} - WA {item.no_wa || '-'}</Text>
      <Text style={styles.muted}>Masuk {item.tanggal_masuk || '-'} - {paymentLabel}</Text>
      {latestBill ? <Text style={styles.muted}>Tagihan terakhir: {monthName(latestBill.bulan)} {latestBill.tahun} - {billStatusLabel(latestBill.status)}</Text> : null}
      {openBills > 0 || verifyBills > 0 || item.akan_jatuh_tempo || isExited ? (
        <View style={styles.badgeRow}>
          {isExited ? <Text style={styles.exitBadge}>Sudah keluar</Text> : null}
          {verifyBills > 0 ? <Text style={styles.verifyBadge}>Perlu cek bukti</Text> : null}
          {openBills > 0 ? <Text style={styles.billBadge}>{hasDebtAfterExit ? `Tunggakan ${money(item.tagihan_aktif_nominal)}` : money(item.tagihan_aktif_nominal)}</Text> : null}
          {item.akan_jatuh_tempo ? <Text style={styles.dueBadge}>Akan jatuh tempo</Text> : null}
        </View>
      ) : null}
      <Text style={styles.linkText}>Buka pembayaran penghuni</Text>
    </Pressable>
  );
}

function BillCard({ bill, rooms, apiBase, updateBillStatus, openImagePreview }) {
  const hasProof = Boolean(bill.bukti_pembayaran || bill.bukti_pembayaran_url);
  const canReview = bill.status === 'menunggu_verifikasi';
  const proofUri = proofImageUrl(bill, apiBase);
  const paid = Number(bill.nominal_terbayar || 0);
  const remaining = Number(bill.sisa_tagihan ?? Math.max(0, Number(bill.nominal || 0) - paid));

  return (
    <View style={styles.card}>
      <View style={styles.rowBetween}>
        <Text style={styles.cardTitle}>Kamar {roomName(rooms, bill.kamar_id)}</Text>
        <Text style={[styles.badge, statusStyle(bill.status)]}>{billStatusLabel(bill.status)}</Text>
      </View>
      <Text style={styles.muted}>Tagihan {monthName(bill.bulan)} {bill.tahun}</Text>
      <Text style={styles.muted}>Jatuh tempo {bill.tanggal_jatuh_tempo || '-'}</Text>
      <Text style={styles.money}>{money(bill.nominal)}</Text>
      {paid > 0 && bill.status !== 'lunas' ? <Text style={styles.muted}>Sudah dibayar {money(paid)}. Sisa {money(remaining)}.</Text> : null}
      {Number(bill.biaya_platform || 0) > 0 ? <Text style={styles.muted}>Biaya QRIS {money(bill.biaya_platform)} - Total {money(bill.total_dibayar)}</Text> : null}
      {hasProof ? (
        <>
          <Text style={styles.label}>Bukti bayar</Text>
          <Pressable onPress={() => openImagePreview(proofUri)} style={({ pressed }) => [pressed && styles.pressed]}>
            <Image source={{ uri: proofUri }} style={styles.proofImage} resizeMode="cover" />
            <Text style={styles.linkText}>Klik untuk lihat gambar penuh</Text>
          </Pressable>
        </>
      ) : (
        <Text style={styles.muted}>Belum ada bukti bayar dari penghuni.</Text>
      )}
      {canReview ? (
        <View style={styles.actionRow}>
          <SecondaryButton title="Tolak" onPress={() => updateBillStatus(bill.id, 'tolak')} style={styles.flexButton} />
          <PrimaryButton title="Verifikasi" onPress={() => updateBillStatus(bill.id, 'verifikasi')} style={styles.flexButton} />
        </View>
      ) : null}
    </View>
  );
}

function OccupantBillCard({ bill, apiBase, updateBillStatus, openImagePreview, onCorrectInitialPayment }) {
  const hasProof = Boolean(bill.bukti_pembayaran || bill.bukti_pembayaran_url);
  const proofUri = proofImageUrl(bill, apiBase);
  const canReview = bill.status === 'menunggu_verifikasi';
  const canCashPay = billIsUnpaid(bill);
  const paid = Number(bill.nominal_terbayar || 0);
  const remaining = Number(bill.sisa_tagihan ?? Math.max(0, Number(bill.nominal || 0) - paid));

  return (
    <View style={styles.billMiniCard}>
      <View style={styles.rowBetween}>
        <Text style={styles.cardTitle}>{monthName(bill.bulan)} {bill.tahun}</Text>
        <Text style={[styles.badge, statusStyle(bill.status)]}>{billStatusLabel(bill.status)}</Text>
      </View>
      <Text style={styles.muted}>Jatuh tempo {bill.tanggal_jatuh_tempo || '-'}</Text>
      <Text style={styles.money}>{money(bill.nominal)}</Text>
      {paid > 0 && bill.status !== 'lunas' ? <Text style={styles.muted}>Sudah dibayar {money(paid)}. Sisa {money(remaining)}.</Text> : null}
      {hasProof ? (
        <Pressable onPress={() => openImagePreview(proofUri)} style={({ pressed }) => [styles.proofThumbRow, pressed && styles.pressed]}>
          <Image source={{ uri: proofUri }} style={styles.proofThumb} />
          <Text style={styles.linkText}>Lihat bukti bayar</Text>
        </Pressable>
      ) : null}
      {canReview ? (
        <View style={styles.actionRow}>
          <SecondaryButton title="Tolak" onPress={() => updateBillStatus(bill.id, 'tolak')} style={styles.flexButton} />
          <PrimaryButton title="Verifikasi" onPress={() => updateBillStatus(bill.id, 'verifikasi')} style={styles.flexButton} />
        </View>
      ) : null}
      {bill.bisa_koreksi_dp ? <SecondaryButton title="Koreksi DP" onPress={() => onCorrectInitialPayment(bill)} style={{ marginTop: spacing.sm }} /> : null}
      {canCashPay ? <PrimaryButton title="Bayar Tunai" onPress={() => updateBillStatus(bill.id, 'lunas')} style={{ marginTop: spacing.sm }} /> : null}
    </View>
  );
}

function ImagePreviewModal({ uri, onClose }) {
  if (!uri) return null;
  return (
    <Modal visible transparent animationType="fade" onRequestClose={onClose}>
      <View style={styles.imagePreviewOverlay}>
        <Image source={{ uri }} style={styles.imagePreview} resizeMode="contain" />
        <PrimaryButton title="Tutup" onPress={onClose} style={styles.imagePreviewButton} />
      </View>
    </Modal>
  );
}

function PaymentAction({ title, description, onPress, primary = false }) {
  return (
    <Pressable onPress={onPress} style={({ pressed }) => [styles.actionCard, primary && styles.actionCardPrimary, pressed && styles.pressed]}>
      <Text style={[styles.actionTitle, primary && styles.actionTitlePrimary]}>{title}</Text>
      <Text style={[styles.actionDescription, primary && styles.actionDescriptionPrimary]}>{description}</Text>
    </Pressable>
  );
}

function ProfitSummary({ summary }) {
  if (!summary) return <Empty text="Ringkasan laba/rugi belum tersedia." />;
  const isProfit = Number(summary.laba_rugi || 0) >= 0;

  return (
    <View style={[styles.profitCard, !isProfit && styles.lossCard]}>
      <Text style={styles.profitLabel}>Laba/Rugi {monthName(summary.bulan)} {summary.tahun}</Text>
      <Text style={[styles.profitValue, !isProfit && styles.lossValue]}>{money(summary.laba_rugi)}</Text>
      <Text style={styles.muted}>{isProfit ? 'Kos sedang untung.' : 'Kos sedang rugi. Cek pengeluaran dan tunggakan penghuni keluar.'}</Text>
      <View style={styles.summaryGrid}>
        <SummaryItem label="Sewa diterima" value={summary.pendapatan_sewa} />
        <SummaryItem label="Pemasukan lain" value={summary.pemasukan_lain} />
        <SummaryItem label="Total masuk" value={summary.total_pemasukan} />
        <SummaryItem label="Pengeluaran" value={summary.pengeluaran} danger />
        <SummaryItem label="Kerugian tunggakan" value={summary.kerugian_tunggakan} danger />
        <SummaryItem label="Margin" value={`${summary.margin_persen}%`} />
      </View>
    </View>
  );
}

function SummaryItem({ label, value, danger = false }) {
  return (
    <View style={styles.summaryItem}>
      <Text style={styles.statLabel}>{label}</Text>
      <Text style={[styles.summaryValue, danger && styles.lossValue]}>{typeof value === 'number' ? money(value) : value}</Text>
    </View>
  );
}

function SimpleCard({ title, lines }) {
  return <View style={styles.card}><Text style={styles.cardTitle}>{title}</Text>{lines.filter(Boolean).map((line, index) => <Text key={index} style={styles.muted}>{line}</Text>)}</View>;
}

function Notice({ text }) {
  return <View style={styles.notice}><Text style={styles.noticeText}>{text}</Text></View>;
}

function Empty({ text }) {
  return <View style={styles.empty}><Text style={styles.muted}>{text}</Text></View>;
}

function ToggleRow({ label, value, onValueChange }) {
  return <View style={styles.toggleRow}><Text style={styles.toggleLabel}>{label}</Text><Switch value={value} onValueChange={onValueChange} trackColor={{ true: colors.gold, false: colors.border }} /></View>;
}

function Segment({ items, labels = {}, value, onChange }) {
  return <View style={styles.segment}>{items.map((item) => <Pressable key={item} onPress={() => onChange(item)} style={[styles.segmentItem, value === item && styles.segmentActive]}><Text style={[styles.segmentText, value === item && styles.segmentTextActive]}>{labels[item] || item}</Text></Pressable>)}</View>;
}

function FilterChips({ items, labels = {}, value, onChange }) {
  return (
    <View style={styles.filterChipWrap}>
      {items.map((item) => {
        const active = value === item;
        return (
          <Pressable key={item} onPress={() => onChange(item)} style={({ pressed }) => [styles.filterChip, active && styles.filterChipActive, pressed && styles.pressed]}>
            <Text style={[styles.filterChipText, active && styles.filterChipTextActive]}>{labels[item] || item}</Text>
          </Pressable>
        );
      })}
    </View>
  );
}

function OptionGrid({ items, value, onChange, emptyText = 'Tidak ada pilihan.' }) {
  if (!items.length) return <Empty text={emptyText} />;
  return <View style={styles.optionGrid}>{items.map((item, index) => <Pressable key={`${item.value || 'all'}-${index}`} onPress={() => onChange(item.value)} style={[styles.option, value === item.value && styles.optionActive]}><Text style={[styles.optionText, value === item.value && styles.optionTextActive]}>{item.label}</Text></Pressable>)}</View>;
}

function money(value) {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(cleanNumber(value) || 0));
}

function cleanNumber(value) {
  return String(value ?? '').replace(/[^0-9]/g, '');
}

function whatsappNumber(value) {
  const digits = cleanNumber(value);
  if (!digits) return '';
  if (digits.startsWith('0')) return `62${digits.slice(1)}`;
  if (digits.startsWith('62')) return digits;
  return `62${digits}`;
}

function formatNumberInput(value) {
  const cleaned = cleanNumber(value);
  if (!cleaned) return '';
  return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(Number(cleaned));
}

function yes(value) {
  return Number(value) === 1 || value === true ? 'Ya' : 'Tidak';
}

function monthName(value) {
  return ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'][Number(value || 1) - 1] || value;
}

function formatDate(value) {
  const [year, month, day] = String(value || today()).split('-');
  return `${Number(day)} ${monthName(month)} ${year}`;
}

function dateParts(value) {
  const [year, month, day] = String(value || today()).split('-').map((item) => Number(item));
  return {
    year: year || Number(thisYear()),
    month: month || Number(thisMonth()),
    day: day || 1,
  };
}

function daysInMonth(year, month) {
  return new Date(year, month, 0).getDate();
}

function pad2(value) {
  return String(value).padStart(2, '0');
}

function billStatusLabel(status) {
  const labels = {
    belum_lunas: 'Belum bayar',
    menunggu_verifikasi: 'Perlu verifikasi',
    lunas: 'Lunas',
    ditolak: 'Ditolak',
    terlambat: 'Terlambat',
  };
  return labels[status] || status;
}

function billSortPriority(status) {
  const priorities = {
    menunggu_verifikasi: 0,
    ditolak: 1,
    terlambat: 2,
    belum_lunas: 3,
    lunas: 4,
  };
  return priorities[status] ?? 9;
}

function billIsUnpaid(bill) {
  return ['belum_lunas', 'terlambat', 'ditolak'].includes(bill.status);
}

function paymentMethodTitle(item) {
  if (item.jenis === 'qris' || item.verification_mode === 'automatic') return 'QRIS Otomatis';
  if (item.jenis === 'bank') return `${item.nama_bank || 'Transfer Bank'} - Manual`;
  return String(item.jenis || 'Metode').toUpperCase();
}

function paymentMethodLines(item) {
  if (item.jenis === 'qris' || item.verification_mode === 'automatic') {
    return [
      item.is_active ? 'Aktif' : 'Nonaktif',
      'Verifikasi otomatis setelah pembayaran berhasil',
      item.gateway_account_id ? `Kode akun: ${item.gateway_account_id}` : 'Kode akun belum diisi',
    ];
  }

  return [
    item.is_active ? 'Aktif' : 'Nonaktif',
    item.nomor_rekening || '-',
    item.atas_nama || '-',
    'Verifikasi manual oleh pemilik kos',
  ];
}

function roomName(rooms, id) {
  return rooms.find((room) => Number(room.id) === Number(id))?.nomor_kamar || id || '-';
}

function roomPickerLabel(room) {
  const number = room?.nomor_kamar || room?.id || '-';
  const price = Number(room?.harga_bulanan || 0) > 0 ? ` - ${money(room.harga_bulanan)}` : '';
  return `Kamar ${number}${price}`;
}

function roomPhotoCacheUri(uri) {
  const extension = uri.split('?')[0].split('.').pop()?.toLowerCase();
  const safeExtension = ['jpg', 'jpeg', 'png', 'webp'].includes(extension) ? extension : 'jpg';
  const cacheKey = uri.replace(/[^a-z0-9]/gi, '_').slice(-120);
  return `${FileSystem.cacheDirectory}balikos-room-${cacheKey}.${safeExtension}`;
}

async function cachedRoomPhotoUri(uri) {
  const cacheUri = roomPhotoCacheUri(uri);
  const cached = await FileSystem.getInfoAsync(cacheUri);
  if (cached.exists) return cacheUri;

  const download = await FileSystem.downloadAsync(uri, cacheUri, { headers: { Accept: 'image/*' } });
  if (download.status < 200 || download.status >= 300) throw new Error(`HTTP ${download.status}`);
  return download.uri;
}

async function prepareRoomPhotoForUpload(asset, index = 0) {
  const attempt = async (maxSide, compress) => {
    const width = Number(asset.width || 0);
    const height = Number(asset.height || 0);
    const largestSide = Math.max(width, height);
    const actions = largestSide > maxSide
      ? [{ resize: width >= height ? { width: maxSide } : { height: maxSide } }]
      : [];
    const result = await ImageManipulator.manipulateAsync(asset.uri, actions, {
      compress,
      format: ImageManipulator.SaveFormat.JPEG,
    });
    const info = await FileSystem.getInfoAsync(result.uri, { size: true });
    return { ...result, fileSize: info.size || 0 };
  };

  try {
    let photo = await attempt(1280, 0.72);
    if (photo.fileSize > MAX_ROOM_PHOTO_UPLOAD_BYTES) {
      photo = await attempt(1024, 0.58);
    }
    if (photo.fileSize > MAX_ROOM_PHOTO_UPLOAD_BYTES) {
      photo = await attempt(900, 0.5);
    }
    if (photo.fileSize > MAX_ROOM_PHOTO_UPLOAD_BYTES) {
      throw new Error(`Foto ${index + 1} masih terlalu besar setelah dikompresi. Pilih foto lain yang lebih ringan.`);
    }

    return {
      ...asset,
      uri: photo.uri,
      width: photo.width,
      height: photo.height,
      fileSize: photo.fileSize,
      fileName: `kamar-${Date.now()}-${index + 1}.jpg`,
      mimeType: 'image/jpeg',
    };
  } catch (error) {
    throw new Error(error.message || `Foto ${index + 1} gagal diproses.`);
  }
}

function prefetchRoomPhotos(rooms, apiBase) {
  const sources = (rooms || [])
    .flatMap((room) => roomPhotos(room, apiBase).map((photo) => (photo.sources || [photo.uri]).find(Boolean)))
    .filter((uri) => /^https?:\/\//.test(uri));
  [...new Set(sources)].slice(0, 12).forEach((uri) => {
    cachedRoomPhotoUri(uri).catch(() => {});
  });
}

function storageUrl(path, apiBase) {
  if (!path) return '';
  const apiRoot = (apiBase || DEFAULT_API).replace(/\/$/, '');
  const absoluteStorage = String(path).match(/^https?:\/\/[^/]+\/storage\/(.+)$/);
  if (absoluteStorage) return `${apiRoot}/media/${absoluteStorage[1]}`;
  const storagePath = String(path).replace(/^\/?storage\//, '');
  if (/^https?:\/\//.test(storagePath)) return storagePath;
  if (storagePath && storagePath !== path) return `${apiRoot}/media/${storagePath}`;
  return `${apiRoot}/media/${path}`;
}

function roomPhotos(room, apiBase = DEFAULT_API) {
  const photos = Array.isArray(room?.fotos) ? room.fotos : [];
  if (photos.length) {
    return photos.map((photo, index) => ({
      key: String(photo.id || photo.path || index),
      uri: storageUrl(photo.path || photo.url, apiBase),
      sources: roomPhotoSources(photo, apiBase),
    })).filter((photo) => photo.uri);
  }
  const uri = storageUrl(room?.foto || room?.foto_url, apiBase);
  return uri ? [{ key: String(room?.foto || uri), uri, sources: roomPhotoSources({ path: room?.foto, url: room?.foto_url }, apiBase) }] : [];
}

function roomPhotoSources(photo, apiBase = DEFAULT_API) {
  const candidates = [
    storageUrl(photo?.path, apiBase),
    storageUrl(photo?.url, apiBase),
    photo?.url,
    storageUrl(photo?.path, DEFAULT_API),
    storageUrl(photo?.url, DEFAULT_API),
  ].filter(Boolean);

  return [...new Set(candidates)];
}

function proofImageUrl(bill, apiBase) {
  if (bill.bukti_pembayaran) return storageUrl(bill.bukti_pembayaran, apiBase);
  if (bill.bukti_pembayaran_url) return storageUrl(bill.bukti_pembayaran_url.replace(/^https?:\/\/[^/]+\/storage\//, ''), apiBase);
  return '';
}

function portalUrl(token, apiBase) {
  const root = (apiBase || DEFAULT_API).replace(/\/api\/balikos\/?$/, '');
  if (/\/\/(10\.0\.2\.2|127\.0\.0\.1|localhost)(:\d+)?$/.test(root)) {
    return `${DEFAULT_PORTAL_ORIGIN}/balikos/portal/${token}`;
  }
  return `${root}/balikos/portal/${token}`;
}

function statusStyle(status) {
  if (status === 'terisi' || status === 'lunas' || status === 'aktif') return { color: colors.success };
  if (status === 'maintenance' || status === 'terlambat' || status === 'ditolak') return { color: colors.danger };
  return { color: colors.goldLight };
}

const styles = StyleSheet.create({
  app: { flex: 1, backgroundColor: colors.background },
  content: { padding: spacing.lg, paddingBottom: 150 },
  modalContent: { padding: spacing.lg, paddingBottom: 320 },
  hero: { borderRadius: 28, padding: spacing.lg, marginBottom: spacing.lg },
  authHeaderClean: { paddingTop: spacing.sm, paddingBottom: spacing.lg, marginBottom: spacing.sm },
  authHero: { borderRadius: 30, padding: spacing.lg, marginBottom: spacing.lg, minHeight: 245, justifyContent: 'space-between' },
  authTopRow: { flexDirection: 'row', alignItems: 'center', gap: spacing.md },
  authLogo: { width: 72, height: 72, borderRadius: 20, borderWidth: 1, borderColor: colors.border },
  authBrand: { color: colors.goldLight, fontSize: 24, fontWeight: '900' },
  authTagline: { color: colors.muted, fontWeight: '700', marginTop: 2 },
  authWelcome: { color: colors.accent, fontSize: 13, fontWeight: '900', textTransform: 'uppercase', marginTop: spacing.lg },
  authTitle: { color: colors.white, fontSize: 28, lineHeight: 34, fontWeight: '900', marginTop: spacing.xs },
  heroLogo: { width: 92, height: 92, borderRadius: 22, marginBottom: spacing.md },
  eyebrow: { color: colors.goldLight, fontSize: 12, letterSpacing: 2, textTransform: 'uppercase', fontWeight: '700' },
  heroEyebrow: { color: colors.accent },
  heroTitle: { color: colors.white, fontSize: 36, fontWeight: '800', marginTop: spacing.xs },
  heroText: { color: '#eaf4ff', lineHeight: 22, marginTop: spacing.xs },
  header: { paddingTop: 42, paddingHorizontal: spacing.lg, paddingBottom: spacing.lg, borderBottomLeftRadius: 22, borderBottomRightRadius: 22, borderBottomWidth: 1, borderColor: colors.border },
  brandRow: { flexDirection: 'row', alignItems: 'center', gap: spacing.md },
  headerLogo: { width: 58, height: 58, borderRadius: 16 },
  headerTitle: { color: colors.text, fontSize: 24, fontWeight: '800', marginTop: 4 },
  headerEditButton: { minHeight: 40, paddingHorizontal: spacing.md, borderRadius: 14, backgroundColor: colors.surface },
  sectionTitle: { color: colors.text, fontSize: 20, fontWeight: '800', marginTop: spacing.md, marginBottom: spacing.sm },
  titleWithInfo: { flexDirection: 'row', alignItems: 'center', gap: spacing.sm, flex: 1 },
  infoButton: { width: 30, height: 30, borderRadius: 15, borderWidth: 1, borderColor: colors.gold, backgroundColor: colors.surfaceAlt, alignItems: 'center', justifyContent: 'center', marginTop: spacing.sm },
  infoButtonText: { color: colors.goldLight, fontSize: 16, fontWeight: '900' },
  label: { color: colors.goldLight, fontWeight: '700', marginBottom: spacing.xs, marginTop: spacing.sm },
  muted: { color: colors.muted, lineHeight: 22 },
  card: { borderWidth: 1, borderColor: colors.border, borderRadius: 20, backgroundColor: colors.surface, padding: spacing.md, marginBottom: spacing.md },
  cardTitle: { color: colors.text, fontSize: 17, fontWeight: '800', marginBottom: 4 },
  actionCard: { borderWidth: 1, borderColor: colors.border, borderRadius: 20, backgroundColor: colors.surface, padding: spacing.md, marginBottom: spacing.md },
  actionCardPrimary: { backgroundColor: colors.gold, borderColor: colors.gold },
  actionTitle: { color: colors.text, fontSize: 17, fontWeight: '800', marginBottom: 4 },
  actionTitlePrimary: { color: colors.white },
  actionDescription: { color: colors.muted, lineHeight: 21 },
  actionDescriptionPrimary: { color: colors.white },
  compactActionPanel: { borderWidth: 1, borderColor: colors.border, borderRadius: 18, backgroundColor: colors.surface, padding: spacing.md, marginBottom: spacing.md },
  helperText: { color: colors.muted, lineHeight: 19, fontSize: 12, marginTop: spacing.xs },
  lockedInfo: { borderWidth: 1, borderColor: colors.border, borderRadius: 18, backgroundColor: colors.surfaceAlt, padding: spacing.md, marginTop: spacing.md, marginBottom: spacing.sm },
  lockedTitle: { color: colors.text, fontSize: 18, fontWeight: '900', marginBottom: 4 },
  helpHero: { borderWidth: 1, borderColor: colors.border, borderRadius: 20, backgroundColor: colors.surfaceAlt, padding: spacing.md, marginBottom: spacing.md },
  helpTitle: { color: colors.text, fontSize: 18, fontWeight: '900', marginBottom: 6 },
  helpStep: { flexDirection: 'row', gap: spacing.md, borderWidth: 1, borderColor: colors.border, borderRadius: 18, backgroundColor: colors.surface, padding: spacing.md, marginBottom: spacing.sm },
  helpStepNumber: { width: 34, height: 34, borderRadius: 17, backgroundColor: colors.gold, alignItems: 'center', justifyContent: 'center' },
  helpStepNumberText: { color: colors.white, fontWeight: '900' },
  helpStepTitle: { color: colors.text, fontSize: 16, fontWeight: '900', marginBottom: 3 },
  helpContactCard: { borderWidth: 1, borderColor: colors.border, borderRadius: 20, backgroundColor: colors.surface, padding: spacing.md, marginTop: spacing.sm, marginBottom: spacing.md },
  emailButton: { minHeight: 48, borderRadius: 16, backgroundColor: colors.gold, alignItems: 'center', justifyContent: 'center', marginTop: spacing.md, paddingHorizontal: spacing.md },
  emailButtonText: { color: colors.white, fontSize: 15, fontWeight: '900' },
  googleButton: { minHeight: 54, borderRadius: 16, backgroundColor: colors.surface, borderWidth: 1, borderColor: colors.border, marginBottom: spacing.md, paddingHorizontal: spacing.md, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: spacing.sm },
  googleIcon: { width: 26, height: 26 },
  googleButtonText: { color: colors.goldLight, fontSize: 16, fontWeight: '800' },
  disabledButton: { opacity: 0.55 },
  authDivider: { flexDirection: 'row', alignItems: 'center', gap: spacing.sm, marginBottom: spacing.md },
  dividerLine: { flex: 1, height: 1, backgroundColor: colors.border },
  dividerText: { color: colors.muted, fontSize: 12, fontWeight: '800' },
  facilities: { color: colors.goldLight, marginTop: spacing.sm },
  linkText: { color: colors.gold, marginTop: spacing.sm, fontWeight: '700' },
  rowBetween: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', gap: spacing.sm },
  actionRow: { flexDirection: 'row', gap: spacing.sm, marginTop: spacing.md },
  flexButton: { flex: 1, minHeight: 44 },
  dangerButton: { borderColor: colors.danger },
  pressed: { opacity: 0.78 },
  badge: { fontWeight: '800', textTransform: 'capitalize' },
  badgeRow: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.sm, marginTop: spacing.sm },
  billBadge: { overflow: 'hidden', borderRadius: 999, backgroundColor: '#fff8e8', color: '#8a5b00', paddingHorizontal: spacing.sm, paddingVertical: spacing.xs, fontWeight: '800', fontSize: 12 },
  verifyBadge: { overflow: 'hidden', borderRadius: 999, backgroundColor: '#e8f7ef', color: colors.success, paddingHorizontal: spacing.sm, paddingVertical: spacing.xs, fontWeight: '800', fontSize: 12 },
  dueBadge: { overflow: 'hidden', borderRadius: 999, backgroundColor: '#eef2ff', color: '#3f4f9f', paddingHorizontal: spacing.sm, paddingVertical: spacing.xs, fontWeight: '800', fontSize: 12 },
  exitBadge: { overflow: 'hidden', borderRadius: 999, backgroundColor: '#eef1f5', color: colors.muted, paddingHorizontal: spacing.sm, paddingVertical: spacing.xs, fontWeight: '800', fontSize: 12 },
  noticeMini: { borderRadius: 14, backgroundColor: '#fff8e8', borderWidth: 1, borderColor: '#f4c76b', paddingHorizontal: spacing.sm, paddingVertical: spacing.xs, marginTop: spacing.xs, marginBottom: spacing.xs },
  noticeMiniText: { color: '#8a5b00', fontSize: 12, fontWeight: '800', lineHeight: 18 },
  billMiniCard: { borderWidth: 1, borderColor: colors.border, borderRadius: 18, backgroundColor: colors.surface, padding: spacing.md, marginBottom: spacing.sm },
  proofThumbRow: { flexDirection: 'row', alignItems: 'center', gap: spacing.sm, marginTop: spacing.sm },
  proofThumb: { width: 58, height: 58, borderRadius: 12, backgroundColor: colors.surfaceAlt },
  roomSummaryRow: { flexDirection: 'row', gap: spacing.sm, marginBottom: spacing.md },
  occupantSummaryGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.sm, marginBottom: spacing.md },
  miniSummary: { flex: 1, borderRadius: 16, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.surface, padding: spacing.sm, alignItems: 'center' },
  miniSummaryCompact: { width: '48%', flex: 0, minHeight: 74, justifyContent: 'center' },
  miniSummaryValue: { color: colors.text, fontSize: 22, fontWeight: '900' },
  miniSummaryLabel: { color: colors.muted, fontSize: 11, fontWeight: '800', marginTop: 2, textAlign: 'center', lineHeight: 14 },
  facilityChipRow: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.xs, marginTop: spacing.sm },
  facilityChip: { overflow: 'hidden', borderRadius: 999, backgroundColor: colors.surfaceAlt, color: colors.goldLight, paddingHorizontal: spacing.sm, paddingVertical: spacing.xs, fontWeight: '800' },
  facilityChipSmall: { overflow: 'hidden', borderRadius: 999, backgroundColor: colors.surfaceAlt, color: colors.goldLight, paddingHorizontal: spacing.sm, paddingVertical: 4, fontWeight: '800', fontSize: 12 },
  activeText: { color: colors.success },
  inactiveText: { color: colors.muted },
  scopeText: { color: colors.muted, fontWeight: '800', marginTop: spacing.xs, marginBottom: spacing.sm },
  dashboardHero: { borderRadius: 24, backgroundColor: '#063a8f', padding: spacing.lg, marginTop: spacing.sm, marginBottom: spacing.md },
  dashboardEyebrow: { color: '#bfdbfe', fontSize: 12, fontWeight: '900', textTransform: 'uppercase' },
  dashboardTitle: { color: colors.white, fontSize: 24, fontWeight: '900', marginTop: spacing.xs },
  dashboardText: { color: '#eaf4ff', lineHeight: 22, marginTop: spacing.sm },
  occupancyTrack: { height: 10, borderRadius: 999, backgroundColor: 'rgba(255,255,255,0.25)', overflow: 'hidden', marginTop: spacing.lg },
  occupancyFill: { height: '100%', borderRadius: 999, backgroundColor: colors.white },
  dashboardMetaRow: { flexDirection: 'row', justifyContent: 'space-between', gap: spacing.sm, marginTop: spacing.sm },
  dashboardMeta: { color: '#eaf4ff', fontWeight: '800', fontSize: 12 },
  softCard: { borderWidth: 1, borderColor: colors.border, borderRadius: 20, backgroundColor: colors.surfaceAlt, padding: spacing.md, marginBottom: spacing.md },
  taskCard: { borderWidth: 1, borderColor: colors.border, borderRadius: 20, backgroundColor: colors.surface, padding: spacing.md, marginBottom: spacing.sm, flexDirection: 'row', alignItems: 'center', gap: spacing.sm },
  taskCardPrimary: { borderColor: colors.gold, backgroundColor: colors.surfaceAlt },
  taskIcon: { width: 38, height: 38, borderRadius: 19, backgroundColor: colors.accent, alignItems: 'center', justifyContent: 'center' },
  taskIconText: { color: '#17233a', fontSize: 18, fontWeight: '900' },
  taskBody: { flex: 1 },
  taskTitle: { color: colors.text, fontSize: 16, fontWeight: '900' },
  taskText: { color: colors.muted, lineHeight: 20, marginTop: 2 },
  taskArrow: { color: colors.goldLight, fontWeight: '900' },
  dashboardGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.sm },
  metricCard: { width: '48%', borderRadius: 18, backgroundColor: colors.surface, borderWidth: 1, borderColor: colors.border, padding: spacing.md, minHeight: 112 },
  metricCardWide: { width: '100%' },
  metricLabel: { color: colors.muted, fontWeight: '700' },
  metricValue: { color: colors.text, fontSize: 25, fontWeight: '900', marginTop: spacing.xs },
  metricHint: { color: colors.goldLight, fontWeight: '700', marginTop: spacing.xs },
  quickGrid: { flexDirection: 'row', gap: spacing.sm, marginBottom: spacing.md },
  quickAction: { flex: 1, minHeight: 54, borderRadius: 16, backgroundColor: colors.surface, borderWidth: 1, borderColor: colors.border, alignItems: 'center', justifyContent: 'center', paddingHorizontal: spacing.sm },
  quickActionText: { color: colors.goldLight, fontWeight: '900', textAlign: 'center', fontSize: 12 },
  statsGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.sm },
  stat: { width: '48%', borderRadius: 18, backgroundColor: colors.surface, borderWidth: 1, borderColor: colors.border, padding: spacing.md },
  statLabel: { color: colors.muted },
  statValue: { color: colors.text, fontSize: 24, fontWeight: '800', marginTop: 4 },
  periodButton: { borderWidth: 1, borderColor: colors.border, borderRadius: 18, backgroundColor: colors.surface, padding: spacing.md, marginBottom: spacing.md },
  periodLabel: { color: colors.muted, fontWeight: '700' },
  periodValue: { color: colors.goldLight, fontSize: 20, fontWeight: '900', marginTop: 4 },
  pickerGroup: { marginBottom: spacing.md },
  yearStepper: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: spacing.sm, marginBottom: spacing.md },
  yearButton: { width: 64, minHeight: 48, paddingHorizontal: 0 },
  yearText: { flex: 1, color: colors.text, fontSize: 28, fontWeight: '900', textAlign: 'center' },
  stepperRow: { minHeight: 54, borderRadius: 16, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.surface, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', padding: 6, marginBottom: spacing.xs },
  stepperButton: { width: 54, minHeight: 42, paddingHorizontal: 0 },
  stepperValue: { flex: 1, color: colors.text, fontSize: 24, fontWeight: '900', textAlign: 'center' },
  profitCard: { borderWidth: 1, borderColor: colors.gold, borderRadius: 20, backgroundColor: colors.surface, padding: spacing.md, marginBottom: spacing.md },
  paymentAutoCard: { backgroundColor: colors.surfaceAlt, borderColor: colors.gold },
  infoBlock: { borderWidth: 1, borderColor: colors.border, borderRadius: 18, backgroundColor: colors.surface, padding: spacing.md, marginBottom: spacing.md },
  infoTitle: { color: colors.text, fontSize: 16, fontWeight: '900', marginBottom: spacing.xs },
  infoText: { color: colors.muted, lineHeight: 22 },
  lossCard: { borderColor: colors.danger },
  profitLabel: { color: colors.goldLight, fontWeight: '800', marginBottom: spacing.xs },
  profitValue: { color: colors.success, fontSize: 28, fontWeight: '900', marginBottom: spacing.xs },
  lossValue: { color: colors.danger },
  summaryGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.sm, marginTop: spacing.md },
  summaryItem: { width: '48%', borderWidth: 1, borderColor: colors.border, borderRadius: 16, padding: spacing.sm },
  summaryValue: { color: colors.text, fontWeight: '800', marginTop: 4 },
  money: { color: colors.goldLight, fontSize: 22, fontWeight: '800', marginTop: spacing.xs },
  segment: { flexDirection: 'row', backgroundColor: colors.surface, borderRadius: 18, borderWidth: 1, borderColor: colors.border, padding: 4, marginBottom: spacing.md },
  segmentItem: { flex: 1, alignItems: 'center', paddingVertical: spacing.sm, borderRadius: 14 },
  segmentActive: { backgroundColor: colors.gold },
  segmentText: { color: colors.muted, fontWeight: '700', textTransform: 'capitalize', fontSize: 12 },
  segmentTextActive: { color: colors.white },
  filterChipWrap: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.sm, marginBottom: spacing.md },
  filterChip: { minHeight: 38, borderRadius: 999, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.surface, paddingHorizontal: spacing.md, alignItems: 'center', justifyContent: 'center' },
  filterChipActive: { backgroundColor: colors.gold, borderColor: colors.gold },
  filterChipText: { color: colors.muted, fontWeight: '800', fontSize: 12 },
  filterChipTextActive: { color: colors.white },
  kosPills: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.sm, marginBottom: spacing.md },
  kosPill: { borderWidth: 1, borderColor: colors.border, borderRadius: 999, paddingHorizontal: spacing.md, paddingVertical: spacing.sm },
  kosPillActive: { backgroundColor: colors.gold, borderColor: colors.gold },
  kosPillText: { color: colors.goldLight, fontWeight: '700' },
  kosPillTextActive: { color: colors.white },
  kosAddPill: { borderColor: colors.gold, borderStyle: 'dashed', backgroundColor: colors.surface },
  kosAddText: { color: colors.gold, fontWeight: '800' },
  kosEditPill: { borderColor: colors.border, backgroundColor: colors.surfaceAlt },
  kosEditText: { color: colors.muted, fontWeight: '800' },
  bottomNavWrap: { position: 'absolute', left: 0, right: 0, bottom: 0, paddingHorizontal: spacing.sm, paddingTop: spacing.sm, backgroundColor: 'transparent' },
  bottomNav: { borderRadius: 22, backgroundColor: colors.surface, borderWidth: 1, borderColor: colors.border, flexDirection: 'row', padding: 5 },
  navItem: { flex: 1, minHeight: 54, alignItems: 'center', justifyContent: 'center', gap: 3, paddingVertical: spacing.xs },
  navText: { color: colors.muted, fontWeight: '700', fontSize: 11 },
  navTextActive: { color: colors.goldLight },
  smallButton: { minHeight: 42, paddingHorizontal: spacing.md },
  cardButton: { minHeight: 42, marginTop: spacing.md },
  modalOverlay: { flex: 1, backgroundColor: 'rgba(0,0,0,0.55)', justifyContent: 'flex-end' },
  modalCard: { maxHeight: '88%', backgroundColor: colors.background, borderTopLeftRadius: 28, borderTopRightRadius: 28 },
  modalCardContent: { padding: spacing.lg, paddingBottom: 320 },
  modalTitle: { color: colors.text, fontSize: 25, fontWeight: '800' },
  toggleRow: { minHeight: 52, flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', borderBottomWidth: 1, borderBottomColor: colors.border },
  toggleLabel: { color: colors.text, fontWeight: '600' },
  preview: { height: 180, borderRadius: 20, marginBottom: spacing.md },
  proofImage: { height: 190, borderRadius: 18, marginTop: spacing.sm, marginBottom: spacing.md, backgroundColor: colors.surfaceAlt },
  roomPhotoGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.sm, marginTop: spacing.sm, marginBottom: spacing.md },
  roomPhotoItem: { width: '48%', position: 'relative' },
  roomPhotoThumb: { width: '100%', height: 120, borderRadius: 16, overflow: 'hidden', backgroundColor: colors.surfaceAlt },
  photoError: { alignItems: 'center', justifyContent: 'center', padding: spacing.sm },
  photoErrorText: { color: colors.muted, fontSize: 12, textAlign: 'center', fontWeight: '700' },
  roomCardImage: { width: '100%', height: 130, borderRadius: 16, marginTop: spacing.sm, marginBottom: spacing.sm, backgroundColor: colors.surfaceAlt },
  removePhotoButton: { position: 'absolute', right: 8, bottom: 8, paddingHorizontal: 10, paddingVertical: 6, borderRadius: 999, backgroundColor: 'rgba(255,255,255,0.92)', borderWidth: 1, borderColor: colors.border },
  removePhotoText: { color: colors.danger, fontWeight: '800', fontSize: 12 },
  imagePreviewOverlay: { flex: 1, backgroundColor: 'rgba(0,0,0,0.92)', padding: spacing.md, justifyContent: 'center' },
  imagePreview: { width: '100%', height: '82%' },
  imagePreviewButton: { marginTop: spacing.md },
  compactDate: { marginBottom: spacing.md },
  dateField: { minHeight: 54, borderRadius: 16, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.surface, paddingHorizontal: spacing.md, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  dateFieldText: { color: colors.text, fontSize: 16, fontWeight: '800' },
  dateFieldHint: { color: colors.goldLight, fontWeight: '800' },
  calendarPanel: { borderWidth: 1, borderColor: colors.border, borderRadius: 18, backgroundColor: colors.surface, padding: spacing.sm, marginTop: spacing.sm },
  calendarHeader: { flexDirection: 'row', alignItems: 'center', gap: spacing.sm, marginBottom: spacing.sm },
  calendarNav: { width: 48, minHeight: 40, paddingHorizontal: 0 },
  calendarTitle: { flex: 1, color: colors.text, fontSize: 17, fontWeight: '900', textAlign: 'center' },
  weekRow: { flexDirection: 'row', marginBottom: spacing.xs },
  weekText: { width: '14.285%', color: colors.muted, fontSize: 11, fontWeight: '800', textAlign: 'center' },
  dayGrid: { flexDirection: 'row', flexWrap: 'wrap' },
  dayCell: { width: '14.285%', aspectRatio: 1, alignItems: 'center', justifyContent: 'center', borderRadius: 12 },
  dayCellActive: { backgroundColor: colors.gold },
  dayCellBlank: { opacity: 0 },
  dayText: { color: colors.text, fontWeight: '700' },
  dayTextActive: { color: colors.white, fontWeight: '900' },
  emptyPhoto: { height: 120, borderRadius: 20, borderWidth: 1, borderColor: colors.border, alignItems: 'center', justifyContent: 'center', marginBottom: spacing.md },
  footer: { flexDirection: 'row', gap: spacing.sm, marginTop: spacing.lg },
  empty: { borderRadius: 20, borderWidth: 1, borderColor: colors.border, padding: spacing.md, marginBottom: spacing.md },
  notice: { borderRadius: 18, backgroundColor: colors.surfaceAlt, borderWidth: 1, borderColor: colors.gold, padding: spacing.md, marginBottom: spacing.md },
  noticeText: { color: colors.goldLight, lineHeight: 21 },
  optionGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.sm, marginBottom: spacing.md },
  option: { borderWidth: 1, borderColor: colors.border, borderRadius: 16, paddingHorizontal: spacing.md, paddingVertical: spacing.sm },
  optionActive: { backgroundColor: colors.gold, borderColor: colors.gold },
  optionText: { color: colors.goldLight, fontWeight: '700' },
  optionTextActive: { color: colors.white },
});
