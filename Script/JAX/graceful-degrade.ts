import AutoComplete from "../components/auto-complete";
import CodeBlock from "../components/code-block";
import CollapseBox from "../components/collapse-box";
import DatePicker from "../components/date-picker";
import Editor from "../components/editor";
import Form from "../components/form";
import IdleClock from "../components/idle-clock";
import ImageGallery from "../components/image-gallery";
import ImageResizer from "../components/image-resizer";
import Link from "../components/link";
import MediaPlayer from "../components/media-player";
import PageList from "../components/page-list";
import Switch from "../components/switch";
import Tabs from "../components/tabs";
import { updateDates } from "./util";

export default function gracefulDegrade(container: HTMLElement) {
  updateDates();

  // Hydrate all components
  [
    AutoComplete,
    CodeBlock,
    CollapseBox,
    DatePicker,
    Editor,
    Form,
    IdleClock,
    ImageGallery,
    ImageResizer,
    Link,
    MediaPlayer,
    PageList,
    Switch,
    Tabs,
  ].forEach((Component) => {
    Component.hydrate(container);
  });
}
